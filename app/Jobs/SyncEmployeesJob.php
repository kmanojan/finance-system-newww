<?php

namespace App\Jobs;

use App\Models\ApiIntegration;
use App\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class SyncEmployeesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected ApiIntegration $integration) {}

    public function handle(): void
    {
        try {
            $request = Http::asJson();
            if ($this->integration->bearer_token) {
                $request->withToken($this->integration->bearer_token);
            }

            $method = strtolower($this->integration->method ?: 'get');
            $response = $request->$method($this->integration->url);

            $response->throw();

            $data = $response->json();
            $employees = $this->integration->response_path
                ? data_get($data, $this->integration->response_path, [])
                : $data;

            if (!is_array($employees)) {
                throw new \Exception("Response path '{$this->integration->response_path}' did not return an array of employees.");
            }

            foreach ($employees as $index => $row) {
                if (!is_array($row)) continue;

                $externalId = $row['id'] ?? $row['employee_id'] ?? $row['uuid'] ?? ($index + 1);
                $firstName  = $row['first_name'] ?? $row['name'] ?? 'Employee';
                $lastName   = $row['last_name'] ?? '';
                $fullName   = $row['full_name'] ?? trim("$firstName $lastName");

                Employee::updateOrCreate(
                    ['external_id' => (string) $externalId],
                    [
                        'employee_code'        => $row['employee_code'] ?? $row['code'] ?? 'EMP-' . $externalId,
                        'first_name'           => $firstName,
                        'last_name'            => $lastName,
                        'full_name'            => $fullName,
                        'personal_email'       => $row['personal_email'] ?? $row['email'] ?? null,
                        'mobile_phone'         => $row['mobile_phone'] ?? $row['phone'] ?? null,
                        'profile_picture_url'  => $row['profile_picture_url'] ?? $row['avatar'] ?? $row['picture'] ?? null,
                        'status'               => isset($row['status']) ? strtolower($row['status']) : 'active',
                        'user_type'            => $row['user_type'] ?? null,
                        'job_position'         => $row['job_position'] ?? $row['title'] ?? $row['position'] ?? 'Team Member',
                        'role'                 => $row['role'] ?? null,
                        'synced_at'            => now(),
                    ]
                );
            }

            $this->integration->update([
                'last_synced_at'    => now(),
                'last_sync_status'  => 'success',
                'last_sync_error'   => null,
            ]);
        } catch (\Throwable $e) {
            $this->integration->update([
                'last_sync_status' => 'failed',
                'last_sync_error'  => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
