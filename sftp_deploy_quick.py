import paramiko
import os
import requests

host = "sftp.wp.com"
username = "ecom-glin680830-qwbos.wordpress.com"
password = "mJboymfoIlw0XyD23hiR"
port = 22

files_to_upload = [
    {
        "local": "wp-content/themes/chao-gang-cheng/includes/ecpay-ecpg-gateway.php",
        "remote": "wp-content/themes/chao-gang-cheng/includes/ecpay-ecpg-gateway.php"
    },
    {
        "local": "wp-content/themes/chao-gang-cheng/functions.php",
        "remote": "wp-content/themes/chao-gang-cheng/functions.php"
    },
    {
        "local": "wp-content/themes/chao-gang-cheng/front-page.php",
        "remote": "wp-content/themes/chao-gang-cheng/front-page.php"
    },
    {
        "local": "wp-content/themes/chao-gang-cheng/header.php",
        "remote": "wp-content/themes/chao-gang-cheng/header.php"
    },
    {
        "local": "wp-content/themes/chao-gang-cheng/includes/ckc-referral.php",
        "remote": "wp-content/themes/chao-gang-cheng/includes/ckc-referral.php"
    },
    {
        "local": "wp-content/themes/chao-gang-cheng/includes/ckc-referral-partner.php",
        "remote": "wp-content/themes/chao-gang-cheng/includes/ckc-referral-partner.php"
    },
    {
        "local": "wp-content/themes/chao-gang-cheng/includes/ckc-referral-admin.php",
        "remote": "wp-content/themes/chao-gang-cheng/includes/ckc-referral-admin.php"
    },
    {
        "local": "wp-content/themes/chao-gang-cheng/includes/ckc-coupons.php",
        "remote": "wp-content/themes/chao-gang-cheng/includes/ckc-coupons.php"
    }
]

print("Connecting to SFTP...")
ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(host, port=port, username=username, password=password, timeout=20)
sftp = ssh.open_sftp()
print("Connected.")

for file_info in files_to_upload:
    print(f"Uploading {file_info['local']} -> {file_info['remote']}...")
    sftp.put(file_info['local'], file_info['remote'])
    print("Uploaded successfully.")

sftp.close()
ssh.close()
