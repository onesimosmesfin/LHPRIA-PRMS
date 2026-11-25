// LHPRIA - Persistent Tunnel Daemon
// Keeps the application online 24/7 with automatic recovery and zero downtime.

const fs = require('fs');
const path = require('path');
const http = require('http');

let localtunnel;
try {
    localtunnel = require('C:/Users/Administrator/AppData/Roaming/npm/node_modules/localtunnel');
} catch (e) {
    localtunnel = require('localtunnel');
}

const PORT = 80;
const PREFERRED_SUBDOMAIN = 'lhpria-addis-2026';
const LOG_FILE = path.join(__dirname, 'tunnel_service.log');
const URL_FILE = path.join(__dirname, 'CURRENT_ONLINE_URL.txt');

function log(msg) {
    const time = new Date().toISOString().replace('T', ' ').substring(0, 19);
    const line = `[${time}] ${msg}\n`;
    console.log(line.trim());
    try {
        fs.appendFileSync(LOG_FILE, line, 'utf8');
    } catch (_) {}
}

let currentTunnel = null;
let reconnectTimer = null;

async function startTunnel() {
    if (reconnectTimer) {
        clearTimeout(reconnectTimer);
        reconnectTimer = null;
    }

    log(`Initiating secure tunnel to port ${PORT} with subdomain "${PREFERRED_SUBDOMAIN}"...`);

    try {
        const tunnel = await localtunnel({
            port: PORT,
            subdomain: PREFERRED_SUBDOMAIN
        });

        currentTunnel = tunnel;
        const onlineUrl = tunnel.url + '/land/';
        log(`>>> SUCCESS: Application is ONLINE at: ${onlineUrl}`);

        // Save URL for external reference
        fs.writeFileSync(URL_FILE, onlineUrl, 'utf8');

        tunnel.on('close', () => {
            log('Tunnel connection closed by remote server. Reconnecting in 3 seconds...');
            scheduleReconnect(3000);
        });

        tunnel.on('error', (err) => {
            log(`Tunnel error encountered: ${err.message}. Reconnecting in 5 seconds...`);
            scheduleReconnect(5000);
        });

    } catch (err) {
        log(`Failed to establish tunnel: ${err.message}. Retrying in 5 seconds...`);
        scheduleReconnect(5000);
    }
}

function scheduleReconnect(delayMs) {
    if (currentTunnel) {
        try { currentTunnel.close(); } catch (_) {}
        currentTunnel = null;
    }
    if (!reconnectTimer) {
        reconnectTimer = setTimeout(startTunnel, delayMs);
    }
}

// Health check every 60 seconds: verify local Apache responds
setInterval(() => {
    http.get(`http://127.0.0.1:${PORT}/land/login.php`, (res) => {
        if (res.statusCode !== 200 && res.statusCode !== 302) {
            log(`Health check warning: local server returned status ${res.statusCode}`);
        }
    }).on('error', (e) => {
        log(`Health check error: Cannot reach local Apache: ${e.message}`);
    });
}, 60000);

// Start on launch
log('=== LHPRIA Permanent Online Daemon Started ===');
startTunnel();
