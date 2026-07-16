import requests
from bs4 import BeautifulSoup
import re
import os

# Configuration
SITE_URL = "https://eshopckc.com"
LOGIN_URL = f"{SITE_URL}/wp-login.php"
ADMIN_URL = f"{SITE_URL}/wp-admin/"
THEME_UPLOAD_PAGE = f"{ADMIN_URL}theme-install.php?action=upload-theme"
THEME_SUBMIT_URL = f"{ADMIN_URL}update.php?action=upload-theme"
THEME_FILE = "chao-gang-cheng.zip"

USERNAME = "glin680830@gmail.com"
PASSWORD = "mJboymfoIlw0XyD23hiR"

session = requests.Session()
# Set headers to look like curl to bypass rate limiting (429)
session.headers.update({
    "User-Agent": "curl/7.81.0"
})

# Start flow
print("Step 1: Starting login loop...")
r = session.get(LOGIN_URL)

logged_in = False
for attempt in range(6):
    print(f"\n--- Login Loop Attempt {attempt+1} ---")
    print("Current URL:", r.url)
    print("Response Status:", r.status_code)
    
    if "wp-admin" in r.url:
        print("Redirected to wp-admin successfully!")
        logged_in = True
        break
        
    soup = BeautifulSoup(r.text, 'html.parser')
    err_div = soup.find('div', id='login_error')
    if err_div:
        print("LOGIN ERROR:", err_div.get_text().strip())
    
    # 1. Check if we are on the math captcha error page
    if soup.find('input', {'name': 'jetpack_protect_process_math_form'}):
        print("Math captcha error page detected! Solving...")
        label = soup.find('label', {'for': 'jetpack_protect_answer'})
        protect_ans_input = soup.find('input', {'name': 'jetpack_protect_answer'})
        if label and protect_ans_input:
            text = label.get_text()
            nums = re.findall(r'\d+', text)
            if len(nums) == 2:
                ans = int(nums[0]) + int(nums[1])
                print(f"Solved math captcha: {nums[0]} + {nums[1]} = {ans}")
                verify_payload = {
                    "jetpack_protect_num": str(ans),
                    "jetpack_protect_answer": protect_ans_input.get('value'),
                    "jetpack_protect_process_math_form": "1"
                }
                r = session.post(LOGIN_URL, data=verify_payload)
                continue
            else:
                print("Could not parse digits from label text:", text)
        else:
            print("Missing label or answer input on error page.")
            
    # 2. Check if we are on the standard login page
    login_form = soup.find('form', id='loginform')
    if login_form:
        print("Standard login form detected. Preparing payload...")
        login_payload = {
            "log": USERNAME,
            "pwd": PASSWORD,
            "wp-submit": "登入",
            "redirect_to": ADMIN_URL,
            "testcookie": "1"
        }
        
        # Check if there is an inline captcha in the login form
        protect_ans_input = soup.find('input', {'name': 'jetpack_protect_answer'})
        if protect_ans_input:
            label = soup.find('label', {'for': 'jetpack_protect_answer'})
            if label:
                text = label.get_text()
                nums = re.findall(r'\d+', text)
                if len(nums) == 2:
                    ans = int(nums[0]) + int(nums[1])
                    print(f"Solved inline login captcha: {nums[0]} + {nums[1]} = {ans}")
                    login_payload["jetpack_protect_num"] = str(ans)
                    login_payload["jetpack_protect_answer"] = protect_ans_input.get('value')
        
        # Submit login
        r = session.post(LOGIN_URL, data=login_payload)
        continue

    print("Unknown page state. Saving to error_response.html...")
    with open("error_response.html", "w") as f:
        f.write(r.text)
    break

if not logged_in:
    print("Error: Login failed! Could not reach wp-admin.")
    exit(1)

print("Success: Logged in successfully!")

# Step 3: Fetch theme upload page to extract nonce
print("\nStep 3: Fetching theme upload page to extract nonce...")
r = session.get(THEME_UPLOAD_PAGE)
soup = BeautifulSoup(r.text, 'html.parser')
nonce_input = soup.find('input', {'name': '_wpnonce'})
if not nonce_input:
    print("Error: Could not find upload nonce.")
    exit(1)

nonce = nonce_input.get('value')
print("Found nonce:", nonce)

# Step 4: Upload theme file
print(f"\nStep 4: Uploading theme file '{THEME_FILE}'...")
upload_data = {
    "_wpnonce": nonce,
    "_wp_http_referer": "/wp-admin/theme-install.php?action=upload-theme",
    "installtheme-upload-submit": "立即安裝"
}
files = {
    "themezip": (THEME_FILE, open(THEME_FILE, "rb"), "application/zip")
}
r = session.post(THEME_SUBMIT_URL, data=upload_data, files=files)
print("Upload status:", r.status_code)

# Check if we need to replace active theme
if "已經存在" in r.text or "already exists" in r.text or "overwrite" in r.text.lower():
    print("Theme already exists. Finding the replace link...")
    soup = BeautifulSoup(r.text, 'html.parser')
    replace_link = None
    for a in soup.find_all('a'):
        href = a.get('href')
        if href and "overwrite" in href:
            replace_link = href
            break
    if not replace_link:
        for a in soup.find_all('a'):
            href = a.get('href')
            if href and "update.php?action=upload-theme" in href:
                replace_link = href
                break
    
    if replace_link:
        if not replace_link.startswith("http"):
            replace_link = ADMIN_URL + replace_link
        replace_link = replace_link.replace("&amp;", "&")
        print("Found replace link:", replace_link)
        r = session.get(replace_link)
        print("Replace request status:", r.status_code)
    else:
        print("Error: Could not find replace link.")
        exit(1)

print("Theme installed/updated successfully!")

# Step 5: Activate
print("\nStep 5: Activating theme...")
themes_page_url = f"{ADMIN_URL}themes.php"
r = session.get(themes_page_url)
soup = BeautifulSoup(r.text, 'html.parser')
# Find activation link
activate_link = None
for a in soup.find_all('a'):
    href = a.get('href')
    if href and "action=activate" in href and "stylesheet=chao-gang-cheng" in href:
        activate_link = href
        break
if activate_link:
    if not activate_link.startswith("http"):
        activate_link = ADMIN_URL + activate_link
    activate_link = activate_link.replace("&amp;", "&")
    print("Activating via:", activate_link)
    session.get(activate_link)
    print("Activation request sent.")
else:
    print("Theme might already be active or link not found.")

# Step 6: Trigger import hook
print("\nStep 6: Triggering remote product import hook...")
import_trigger_url = f"{SITE_URL}/?import_chao_gang_cheng_products=secret123"
r = session.get(import_trigger_url)
print("Import status code:", r.status_code)
if "import_success" in r.text:
    print("SUCCESS: Products imported successfully!")
else:
    print("Import response:")
    print(r.text[:200])

print("Deployment completed successfully!")
