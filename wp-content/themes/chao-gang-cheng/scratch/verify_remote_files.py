import paramiko

host = "sftp.wp.com"
username = "ecom-glin680830-qwbos.wordpress.com"
password = "v35iuRsPnvtJtNOIYaWN"
port = 22

print("Connecting to SFTP...")
ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(host, port=port, username=username, password=password, timeout=20)
sftp = ssh.open_sftp()
print("Success: Connected to SFTP.")

remote_file = "wp-content/themes/chao-gang-cheng/woocommerce/loop/no-products-found.php"

try:
    stat = sftp.stat(remote_file)
    print(f"File exists on remote SFTP! Size: {stat.st_size} bytes")
    
    # Read content
    with sftp.open(remote_file, 'r') as f:
        content = f.read()
        print("--- File Content Snippet ---")
        print(content[:300])
        print("----------------------------")
except IOError as e:
    print(f"Error: Remote file does not exist or cannot be read. Detail: {e}")

sftp.close()
ssh.close()
