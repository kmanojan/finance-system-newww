import re

with open('schema.sql', 'r') as f:
    sql = f.read()

# Remove CREATE DATABASE and USE
sql = re.sub(r'CREATE DATABASE IF NOT EXISTS finance_system;', '', sql)
sql = re.sub(r'USE finance_system;', '', sql)

# Replace INT AUTO_INCREMENT PRIMARY KEY with INTEGER PRIMARY KEY AUTOINCREMENT
sql = re.sub(r'INT\s+AUTO_INCREMENT\s+PRIMARY\s+KEY', 'INTEGER PRIMARY KEY AUTOINCREMENT', sql)

# Replace ENUM(...) with VARCHAR(255)
sql = re.sub(r"ENUM\([^)]+\)", "VARCHAR(255)", sql)

# Remove ON UPDATE CURRENT_TIMESTAMP
sql = re.sub(r'ON UPDATE CURRENT_TIMESTAMP', '', sql)

# Fix other potential SQLite syntax issues
# If there are boolean, they are fine as TINYINT or BOOLEAN in SQLite

with open('schema_sqlite.sql', 'w') as f:
    f.write(sql)
