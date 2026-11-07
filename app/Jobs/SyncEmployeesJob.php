<?php

namespace App\Jobs;

use App\Models\ApiIntegration;
use App\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SyncEmployeesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected ApiIntegration $integration) {}

    public function handle(): int
    {
        try {
            $token = preg_replace('/^Bearer\s+/i', '', trim($this->integration->bearer_token ?? ''));
            $request = Http::acceptJson()->asJson()->connectTimeout(10)->timeout(20)->withoutRedirecting();
            if ($token !== '') {
                $request->withToken($token);
            }
            $url = $this->integration->url;
            $base = explode('?', explode('#', $url, 2)[0], 2)[0];
            parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $parameters);
            // The finance directory includes former employees and their outstanding payables.
            if (parse_url($url, PHP_URL_HOST) === 'nexus-api.apptimus.io') {
                unset($parameters['status']);
            }
            $employees = [];
            $fingerprints = [];
            $page = 1;
            do {
                $pageUrl = $base.'?'.http_build_query(array_replace($parameters, ['page' => $page]));
                $response = $request->send(strtoupper($this->integration->method ?: 'GET'), $pageUrl);
                if (! $response->successful()) {
                    throw new \RuntimeException('HR API returned HTTP '.$response->status().'. Check the URL, token, and API availability.');
                }
                $data = $response->json();
                $rows = $this->integration->response_path
                    ? data_get($data, $this->integration->response_path)
                    : (is_array($data) && array_is_list($data) ? $data : data_get($data, 'data'));
                if (is_array($rows) && isset($rows['data'])) {
                    $rows = $rows['data'];
                }
                if (! is_array($rows) || ! array_is_list($rows)) {
                    throw new \RuntimeException('The configured data path did not return an employee list. For Nexus, use data.');
                }
                foreach ($rows as $row) {
                    if (! is_array($row) || ! isset($row['id']) || ! is_numeric($row['id'])) {
                        throw new \RuntimeException('The HR API returned an employee without a valid numeric ID. No employees were changed.');
                    }
                }
                $fingerprint = hash('sha256', json_encode(array_column($rows, 'id')));
                if ($rows && isset($fingerprints[$fingerprint])) {
                    throw new \RuntimeException('The HR API repeated a page. No employees were changed.');
                }
                $fingerprints[$fingerprint] = true;
                $employees = array_merge($employees, $rows);
                $lastPage = data_get($data, 'meta.last_page', data_get($data, 'last_page', data_get($data, 'data.last_page')));
                $next = data_get($data, 'links.next', data_get($data, 'next_page_url', data_get($data, 'data.next_page_url')));
                $more = $lastPage !== null ? $page < (int) $lastPage : ! empty($next);
                if ($more && (! $rows || $page >= 1000)) {
                    throw new \RuntimeException('The HR API pagination is incomplete. No employees were changed.');
                }
                $page++;
            } while ($more);

            // Fetch every page before changing records, so a failed later page leaves no partial import.
            DB::transaction(function () use ($employees) {
                foreach ($employees as $row) {
                    $externalId = $row['id'];
                    $firstName = $row['first_name'] ?? $row['name'] ?? 'Employee';
                    $lastName = $row['last_name'] ?? '';
                    Employee::updateOrCreate(['external_id' => (string) $externalId], [
                        'employee_code' => $row['employee_code'] ?? $row['code'] ?? 'EMP-'.$externalId,
                        'first_name' => $firstName, 'last_name' => $lastName,
                        'full_name' => $row['full_name'] ?? trim("$firstName $lastName"),
                        'personal_email' => $row['personal_email'] ?? $row['email'] ?? null,
                        'mobile_phone' => $row['mobile_phone'] ?? $row['phone'] ?? null,
                        'profile_picture_url' => $row['profile_picture_url'] ?? $row['avatar'] ?? $row['picture'] ?? null,
                        'status' => strtolower($row['status'] ?? 'active'), 'user_type' => $row['user_type'] ?? null,
                        'job_position' => $row['job_position'] ?? $row['title'] ?? $row['position'] ?? 'Team Member',
                        'role' => $row['role'] ?? null, 'synced_at' => now(),
                    ]);
                }
                $this->integration->update(['last_synced_at' => now(), 'last_sync_status' => 'success', 'last_sync_error' => null]);
            });

            return count(array_unique(array_column($employees, 'id')));
        } catch (\Throwable $error) {
            // Never save response bodies, request headers, credentials, or employee data in sync errors.
            $message = $error instanceof ConnectionException
                ? 'Unable to connect to the HR API. Please retry.'
                : (get_class($error) === \RuntimeException::class ? $error->getMessage() : 'Employee sync failed. Check the integration settings and retry.');
            $this->integration->update(['last_sync_status' => 'failed', 'last_sync_error' => $message]);
            throw new \RuntimeException($message);
        }
    }
}
