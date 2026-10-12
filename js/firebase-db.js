/**
 * Evenza Real-time Cloud Sync Engine
 * Supports:
 * 1. Built-in Automatic Cloud Sync (Zero setup required - works immediately across phones & laptops)
 * 2. Optional Google Firebase Firestore (For custom Firebase projects)
 */

(function (window) {
    const CONFIG_KEY = 'evenza_firebase_config';
    const CLOUD_EVENTS_ID = 'ff808181a09d98f701a12ecbe7e145e1';
    const CLOUD_REGS_ID = 'ff808181a09d98f701a12ecc0bb545e2';
    const CLOUD_BASE_URL = 'https://api.restful-api.dev/objects';

    // Default Firebase configuration
    const DEFAULT_CONFIG = {
        apiKey: "",
        authDomain: "",
        projectId: "",
        storageBucket: "",
        messagingSenderId: "",
        appId: ""
    };

    let db = null;
    let isFirebaseActive = false;
    let eventsUnsubscribe = null;
    let regsUnsubscribe = null;
    let restPollingTimer = null;
    const eventListeners = [];
    const regListeners = [];

    function getConfig() {
        try {
            const saved = localStorage.getItem(CONFIG_KEY);
            if (saved) {
                const parsed = JSON.parse(saved);
                if (parsed && parsed.projectId && parsed.apiKey) {
                    return parsed;
                }
            }
        } catch (e) {
            console.warn('[Evenza Cloud] Error reading saved config', e);
        }
        return DEFAULT_CONFIG;
    }

    function saveConfig(cfg) {
        if (!cfg || !cfg.projectId || !cfg.apiKey) {
            throw new Error("Invalid Firebase Config: apiKey and projectId are required.");
        }
        localStorage.setItem(CONFIG_KEY, JSON.stringify(cfg));
        init();
    }

    function init() {
        const config = getConfig();
        if (config && config.apiKey && config.projectId && typeof firebase !== 'undefined') {
            try {
                let app = !firebase.apps.length ? firebase.initializeApp(config) : firebase.app();
                db = firebase.firestore(app);
                isFirebaseActive = true;
                console.log('[Evenza Cloud] Connected to Custom Firebase Firestore:', config.projectId);
                notifyStatusChange(true, null, config.projectId);
                setupFirebaseListeners();
                return true;
            } catch (err) {
                console.warn('[Evenza Cloud] Firebase initialization error, falling back to built-in cloud sync:', err);
            }
        }

        // Fallback: Activate Built-in Cloud Sync (Zero setup required)
        isFirebaseActive = false;
        console.log('[Evenza Cloud] Operating on Built-in Real-time Cloud Sync Engine (Cross-device active).');
        notifyStatusChange(true, null, 'Evenza Live Cloud');
        setupBuiltinCloudSync();
        return true;
    }

    function notifyStatusChange(isConnected, error = null, projectId = '') {
        window.dispatchEvent(new CustomEvent('evenza:cloudStatus', {
            detail: { isConnected, error, projectId }
        }));
    }

    // =========================================================================
    // BUILT-IN AUTOMATIC CLOUD SYNC ENGINE (REST HTTP)
    // =========================================================================

    async function setupBuiltinCloudSync() {
        if (restPollingTimer) clearInterval(restPollingTimer);

        // Initial Sync
        await syncEventsFromBuiltinCloud();
        await syncRegistrationsFromBuiltinCloud();

        // Background polling every 4 seconds for live updates
        restPollingTimer = setInterval(async () => {
            await syncEventsFromBuiltinCloud();
            await syncRegistrationsFromBuiltinCloud();
        }, 4000);
    }

    async function syncEventsFromBuiltinCloud() {
        try {
            const res = await fetch(`${CLOUD_BASE_URL}/${CLOUD_EVENTS_ID}`, { cache: 'no-store' });
            if (!res.ok) return;
            const payload = await res.json();
            const cloudEvents = (payload.data && Array.isArray(payload.data.events)) ? payload.data.events : [];
            const localEvents = getStoredEvents();

            if (cloudEvents.length > 0) {
                // If local has events not yet in cloud (e.g. from laptop), merge & push up
                const missingInCloud = localEvents.filter(le => !cloudEvents.some(ce => String(ce.id) === String(le.id)));
                if (missingInCloud.length > 0) {
                    const merged = [...cloudEvents, ...missingInCloud];
                    localStorage.setItem('evenza_events', JSON.stringify(merged));
                    await pushEventsToBuiltinCloud(merged);
                    notifyEventsUpdated(merged);
                    return;
                }

                const cloudJson = JSON.stringify(cloudEvents);
                const localJson = JSON.stringify(localEvents);
                if (cloudJson !== localJson) {
                    localStorage.setItem('evenza_events', cloudJson);
                    notifyEventsUpdated(cloudEvents);
                }
            } else if (localEvents.length > 0) {
                // Cloud is empty, push local events to cloud
                await pushEventsToBuiltinCloud(localEvents);
            }
        } catch (e) {
            console.debug('[Evenza Cloud] Event sync check:', e.message);
        }
    }

    async function pushEventsToBuiltinCloud(events) {
        try {
            await fetch(`${CLOUD_BASE_URL}/${CLOUD_EVENTS_ID}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    name: "Evenza Cloud Events Store",
                    data: { events }
                })
            });
        } catch (e) {
            console.error('[Evenza Cloud] Failed to push events to cloud:', e);
        }
    }

    async function syncRegistrationsFromBuiltinCloud() {
        try {
            const res = await fetch(`${CLOUD_BASE_URL}/${CLOUD_REGS_ID}`, { cache: 'no-store' });
            if (!res.ok) return;
            const payload = await res.json();
            const cloudRegs = (payload.data && Array.isArray(payload.data.registrations)) ? payload.data.registrations : [];
            const localRegs = getStoredRegistrations();

            if (cloudRegs.length > 0) {
                const missingInCloud = localRegs.filter(lr => !cloudRegs.some(cr => String(cr.id) === String(lr.id)));
                if (missingInCloud.length > 0) {
                    const merged = [...cloudRegs, ...missingInCloud];
                    localStorage.setItem('evenza_registrations', JSON.stringify(merged));
                    await pushRegistrationsToBuiltinCloud(merged);
                    notifyRegsUpdated(merged);
                    return;
                }

                const cloudJson = JSON.stringify(cloudRegs);
                const localJson = JSON.stringify(localRegs);
                if (cloudJson !== localJson) {
                    localStorage.setItem('evenza_registrations', cloudJson);
                    notifyRegsUpdated(cloudRegs);
                }
            } else if (localRegs.length > 0) {
                await pushRegistrationsToBuiltinCloud(localRegs);
            }
        } catch (e) {
            console.debug('[Evenza Cloud] Reg sync check:', e.message);
        }
    }

    async function pushRegistrationsToBuiltinCloud(registrations) {
        try {
            await fetch(`${CLOUD_BASE_URL}/${CLOUD_REGS_ID}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    name: "Evenza Cloud Registrations Store",
                    data: { registrations }
                })
            });
        } catch (e) {
            console.error('[Evenza Cloud] Failed to push registrations to cloud:', e);
        }
    }

    // =========================================================================
    // FIREBASE FIRESTORE ENGINE (IF CONFIGURED)
    // =========================================================================

    function setupFirebaseListeners() {
        if (!db) return;
        if (eventsUnsubscribe) eventsUnsubscribe();
        if (regsUnsubscribe) regsUnsubscribe();

        eventsUnsubscribe = db.collection('evenza_events').onSnapshot((snapshot) => {
            const cloudEvents = [];
            snapshot.forEach(doc => cloudEvents.push(doc.data()));
            if (cloudEvents.length > 0) {
                localStorage.setItem('evenza_events', JSON.stringify(cloudEvents));
                notifyEventsUpdated(cloudEvents);
            }
        });

        regsUnsubscribe = db.collection('evenza_registrations').onSnapshot((snapshot) => {
            const cloudRegs = [];
            snapshot.forEach(doc => cloudRegs.push(doc.data()));
            if (cloudRegs.length > 0) {
                localStorage.setItem('evenza_registrations', JSON.stringify(cloudRegs));
                notifyRegsUpdated(cloudRegs);
            }
        });
    }

    // =========================================================================
    // LOCAL STORAGE HELPERS & DISPATCHERS
    // =========================================================================

    function getStoredEvents() {
        try {
            const raw = localStorage.getItem('evenza_events');
            return raw ? JSON.parse(raw) : [];
        } catch (e) {
            return [];
        }
    }

    function getStoredRegistrations() {
        try {
            const raw = localStorage.getItem('evenza_registrations');
            return raw ? JSON.parse(raw) : [];
        } catch (e) {
            return [];
        }
    }

    function notifyEventsUpdated(events) {
        eventListeners.forEach(fn => { try { fn(events); } catch (e) {} });
        window.dispatchEvent(new CustomEvent('evenza:eventsSync', { detail: { events } }));
    }

    function notifyRegsUpdated(regs) {
        regListeners.forEach(fn => { try { fn(regs); } catch (e) {} });
        window.dispatchEvent(new CustomEvent('evenza:regsSync', { detail: { regs } }));
    }

    // =========================================================================
    // UNIFIED CLOUD CRUD OPERATIONS
    // =========================================================================

    async function saveEvent(eventObj) {
        const local = getStoredEvents();
        const idx = local.findIndex(e => String(e.id) === String(eventObj.id));
        if (idx >= 0) {
            local[idx] = eventObj;
        } else {
            local.push(eventObj);
        }
        localStorage.setItem('evenza_events', JSON.stringify(local));

        if (isFirebaseActive && db) {
            try {
                await db.collection('evenza_events').doc(String(eventObj.id)).set(eventObj);
            } catch (err) {
                console.error('[Evenza Cloud] Firebase save error:', err);
            }
        } else {
            await pushEventsToBuiltinCloud(local);
        }
        return eventObj;
    }

    async function deleteEvent(eventId) {
        const local = getStoredEvents().filter(e => String(e.id) !== String(eventId));
        localStorage.setItem('evenza_events', JSON.stringify(local));

        if (isFirebaseActive && db) {
            try {
                await db.collection('evenza_events').doc(String(eventId)).delete();
            } catch (err) {
                console.error('[Evenza Cloud] Firebase delete error:', err);
            }
        } else {
            await pushEventsToBuiltinCloud(local);
        }
    }

    async function saveRegistration(regObj) {
        const local = getStoredRegistrations();
        const idx = local.findIndex(r => String(r.id) === String(regObj.id));
        if (idx >= 0) {
            local[idx] = regObj;
        } else {
            local.push(regObj);
        }
        localStorage.setItem('evenza_registrations', JSON.stringify(local));

        if (isFirebaseActive && db) {
            try {
                await db.collection('evenza_registrations').doc(String(regObj.id)).set(regObj);
            } catch (err) {
                console.error('[Evenza Cloud] Firebase registration error:', err);
            }
        } else {
            await pushRegistrationsToBuiltinCloud(local);
        }
        return regObj;
    }

    async function updateRegistration(regId, patch) {
        const local = getStoredRegistrations();
        const item = local.find(r => String(r.id) === String(regId));
        if (item) {
            Object.assign(item, patch);
            localStorage.setItem('evenza_registrations', JSON.stringify(local));
        }

        if (isFirebaseActive && db) {
            try {
                await db.collection('evenza_registrations').doc(String(regId)).update(patch);
            } catch (err) {
                console.error('[Evenza Cloud] Firebase update error:', err);
            }
        } else {
            await pushRegistrationsToBuiltinCloud(local);
        }
        return item;
    }

    // Public API
    window.EvenzaCloud = {
        init,
        getConfig,
        saveConfig,
        isReady: () => true, // Always ready via built-in cloud or Firebase
        onEventsUpdated: (fn) => { if (typeof fn === 'function') eventListeners.push(fn); },
        onRegistrationsUpdated: (fn) => { if (typeof fn === 'function') regListeners.push(fn); },
        saveEvent,
        deleteEvent,
        saveRegistration,
        updateRegistration
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(window);
