import os
import sys
import time
import re
import subprocess
import datetime

WORKSPACE = r"C:\Users\Administrator\OneDrive\Desktop\LHPRIA deploy"
LOG_FILE = os.path.join(WORKSPACE, "tunnel_service.log")
URL_FILE = os.path.join(WORKSPACE, "CURRENT_ONLINE_URL.txt")
DEPLOYMENT_MD = os.path.join(WORKSPACE, "DEPLOYMENT.md")

def log(msg):
    now = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    entry = f"[{now}] {msg}\n"
    print(entry.strip())
    try:
        with open(LOG_FILE, "a", encoding="utf-8") as f:
            f.write(entry)
    except Exception:
        pass

def update_files(url):
    full_url = url.rstrip("/") + "/land/"
    try:
        with open(URL_FILE, "w", encoding="utf-8") as f:
            f.write(full_url + "\n")
        log(f">>> ACTIVE LIVE URL RECORDED: {full_url}")
    except Exception as e:
        log(f"Error saving URL file: {e}")

    try:
        if os.path.exists(DEPLOYMENT_MD):
            with open(DEPLOYMENT_MD, "r", encoding="utf-8") as f:
                content = f.read()
            # Replace old pinggy urls
            updated = re.sub(
                r"https://[a-zA-Z0-9\.\-]+\.(?:free\.pinggy\.net|run\.pinggy-free\.link)/land/?",
                full_url,
                content
            )
            with open(DEPLOYMENT_MD, "w", encoding="utf-8") as f:
                f.write(updated)
            log("DEPLOYMENT.md updated with fresh active URL.")
    except Exception as e:
        log(f"Error updating DEPLOYMENT.md: {e}")

def ensure_services():
    try:
        subprocess.run(["sc", "start", "Apache2.4"], capture_output=True)
        subprocess.run(["sc", "start", "mysql"], capture_output=True)
    except Exception as e:
        log(f"Service check notice: {e}")

def run_supervisor():
    log("=== LHPRIA 24/7 Independent Service Started ===")
    ensure_services()

    ssh_cmd = [
        "ssh",
        "-p", "443",
        "-R0:127.0.0.1:80",
        "-o", "StrictHostKeyChecking=no",
        "-o", "ServerAliveInterval=30",
        "-o", "ServerAliveCountMax=3",
        "free.pinggy.io"
    ]

    while True:
        log("Establishing secure HTTPS tunnel connection...")
        try:
            proc = subprocess.Popen(
                ssh_cmd,
                stdout=subprocess.PIPE,
                stderr=subprocess.STDOUT,
                text=True,
                bufsize=1
            )

            for line in iter(proc.stdout.readline, ""):
                clean_line = line.strip()
                if clean_line:
                    log(clean_line)
                    match = re.search(r"https://[a-zA-Z0-9\.\-]+(?:free\.pinggy\.net|run\.pinggy-free\.link)", clean_line)
                    if match:
                        found_url = match.group(0)
                        update_files(found_url)

            proc.wait()
            log(f"Tunnel session ended with code {proc.returncode}. Reconnecting in 2 seconds...")
        except Exception as e:
            log(f"Supervisor encountered error: {e}. Reconnecting in 3 seconds...")
            time.sleep(3)

        time.sleep(2)

if __name__ == "__main__":
    run_supervisor()
