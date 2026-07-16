import paramiko
import os
import requests

host = "sftp.wp.com"
username = "ecom-glin680830-qwbos.wordpress.com"
password = "mJboymfoIlw0XyD23hiR"
port = 22

local_theme_dir = "wp-content/themes/chao-gang-cheng"
remote_theme_dir = "wp-content/themes/chao-gang-cheng"
trigger_url = "https://ecom-glin680830-qwbos.wpcomstaging.com/?import_chao_gang_cheng_products=secret123"

def sftp_mkdir_p(sftp, remote_directory):
    """Helper to recursively create remote directories like mkdir -p"""
    dirs = []
    dir_path = remote_directory
    while len(dir_path) > 1:
        dirs.append(dir_path)
        dir_path, _ = os.path.split(dir_path)
    
    if dir_path and dir_path not in dirs:
        dirs.append(dir_path)
        
    dirs.reverse()
    for d in dirs:
        try:
            sftp.mkdir(d)
            print(f"Created remote directory: {d}")
        except IOError:
            # Directory already exists
            pass

print("Step 1: Connecting to SFTP...")
ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(host, port=port, username=username, password=password, timeout=20)
sftp = ssh.open_sftp()
print("Success: Connected to SFTP.")

print("\nStep 2: Uploading theme files...")
# We will walk through the local theme directory and upload files recursively
for root, dirs, files in os.walk(local_theme_dir):
    for filename in files:
        # Skip macOS metadata files
        if filename.startswith("._") or filename == ".DS_Store":
            continue
            
        local_path = os.path.join(root, filename)
        
        # Calculate relative path
        relative_path = os.path.relpath(local_path, local_theme_dir)
        remote_path = os.path.join(remote_theme_dir, relative_path)
        remote_dir = os.path.dirname(remote_path)
        
        # Ensure remote directory exists
        sftp_mkdir_p(sftp, remote_dir)
        
        print(f"Uploading {local_path} -> {remote_path}...")
        sftp.put(local_path, remote_path)

print("Success: All theme files uploaded.")

print("\nStep 3: Activating theme via WP-CLI on SSH...")
try:
    # Run wp-cli command via SSH channel
    command = "wp theme activate chao-gang-cheng"
    print(f"Running remote command: {command}")
    stdin, stdout, stderr = ssh.exec_command(command)
    
    out_lines = stdout.read().decode('utf-8')
    err_lines = stderr.read().decode('utf-8')
    
    if out_lines:
        print(f"Stdout:\n{out_lines}")
    if err_lines:
        print(f"Stderr:\n{err_lines}")
        
    if "Success" in out_lines or "already active" in out_lines.lower():
        print("Success: Theme activated on remote server.")
    else:
        print("Warning: Activation output did not confirm success. We will proceed to trigger import.")
except Exception as e:
    print(f"Error executing SSH command: {e}")

sftp.close()
ssh.close()

print("\nStep 4: Triggering remote product and category import hook...")
print(f"Requesting: {trigger_url}")
try:
    response = requests.get(trigger_url, timeout=30)
    print(f"Response Status: {response.status_code}")
    if "import_success" in response.text:
        print("SUCCESS: Products, categories, and images have been fully imported on the remote server!")
    else:
        print("Verification: Checking if site home page displays the products...")
        home_check = requests.get("https://ecom-glin680830-qwbos.wpcomstaging.com", timeout=15)
        if "太陽百匯" in home_check.text or "牛肉爐" in home_check.text:
            print("SUCCESS: Products are rendering successfully on the home page!")
        else:
            print("Warning: Products are not visible yet. You may need to manually activate the theme and visit the import link:")
            print(trigger_url)
except Exception as e:
    print(f"Error triggering remote import: {e}")

print("\nDeployment execution finished!")
