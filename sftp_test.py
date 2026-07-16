import paramiko
import sys

hosts = ["sftp.wp.com", "ecom-glin680830-qwbos.wpcomstaging.com"]
usernames = ["glin680830@gmail.com", "glin680830"]
password = "As789650#$"
port = 22

print("Starting SFTP connection tests...")

for host in hosts:
    for username in usernames:
        print(f"\nTrying connection to {host} as {username}...")
        ssh = paramiko.SSHClient()
        ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        try:
            ssh.connect(host, port=port, username=username, password=password, timeout=10)
            print(f"SUCCESS! Connected to {host} as {username}!")
            
            # Let's check files
            sftp = ssh.open_sftp()
            print("Listing remote root directory:")
            print(sftp.listdir('.'))
            
            # Save successful credentials to a file so we can use them later
            with open("sftp_success.txt", "w") as f:
                f.write(f"host={host}\nusername={username}\npassword={password}\nport={port}\n")
            
            sftp.close()
            ssh.close()
            sys.exit(0)
        except paramiko.AuthenticationException:
            print(f"Auth Failed: Authentication failed for {username} on {host}.")
        except Exception as e:
            print(f"Error connecting to {host} as {username}: {e}")

print("\nAll default test combinations failed. SFTP credentials provided might need a specific SFTP username from WordPress.com dashboard.")
sys.exit(1)
