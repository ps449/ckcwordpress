import paramiko

host = "sftp.wp.com"
username = "ecom-glin680830-qwbos.wordpress.com"
password = "v35iuRsPnvtJtNOIYaWN"
port = 22

print("Connecting to SFTP to inspect remote directory...")
ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
try:
    ssh.connect(host, port=port, username=username, password=password, timeout=15)
    print("SUCCESS! Connected.")
    sftp = ssh.open_sftp()
    
    print("\nListing '.' directory:")
    files = sftp.listdir('.')
    print(files)
    
    # Let's search if wp-content is in '.'
    if "wp-content" in files:
        print("wp-content is in current directory.")
    else:
        # Search recursively or look in common directories like 'htdocs'
        for f in files:
            try:
                subfiles = sftp.listdir(f)
                print(f"Listing '{f}': {subfiles}")
            except Exception:
                pass
                
    sftp.close()
    ssh.close()
except Exception as e:
    print(f"Error: {e}")
