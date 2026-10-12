/**
 * Evenza Real-time Cloud Sync Engine (Firebase Firestore)
 * Enables seamless, instant real-time synchronization of Events & Registrations
 * across all mobile devices, tablets, and laptops.
 */

(function (window) {
    const CONFIG_KEY = 'evenza_firebase_config';

    // Default configuration (can be updated dynamically via UI or hardcoded here)
    const DEFAULT_CONFIG = {
        apiKey: "",
        authDomain: "",
        projectId: "",
        storageBucket: "",
        messagingSenderId: "",
        appId: ""
    };

    let db = null;
    let initialized = false;
    let eventsUnsubscribe = null;
    let regsUnsubscribe = null;
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
        if (!config || !config.apiKey || !config.projectId) {
            console.log('[Evenza Cloud] Operating in local storage mode (Firebase not yet configured).');
            notifyStatusChange(false);
            return false;
        }

        if (typeof firebase === 'undefined') {
            console.warn('[Evenza Cloud] Firebase SDK not loaded in HTML.');
            notifyStatusChange(false);
            return false;
        }

        try {
            // Check if app already initialized
            let app;
            if (!firebase.apps.length) {
                app = firebase.initializeApp(config);
            } else {
                app = firebase.app();
            }

            db = firebase.firestore(app);
            initialized = true;
            console.log('[Evenza Cloud] Firebase Firestore initialized successfully for project:', config.projectId);
            notifyStatusChange(true);

            setupRealtimeListeners();
            return true;
        } catch (err) {
            console.error('[Evenza Cloud] Initialization error:', err);
            notifyStatusChange(false, err.message);
            return false;
        }
    }

    function notifyStatusChange(isConnected, error = null) {
        window.dispatchEvent(new CustomEvent('evenza:cloudStatus', {
            detail: { isConnected, error, projectId: getConfig().projectId }
        }));
    }

    function setupRealtimeListeners() {
        if (!db) return;

        // Unsubscribe previous listeners if any
        if (eventsUnsubscribe) eventsUnsubscribe();
        if (regsUnsubscribe) regsUnsubscribe();

        // 1. Listen for Events collection
        eventsUnsubscribe = db.collection('evenza_events').onSnapshot((snapshot) => {
            const cloudEvents = [];
            snapshot.forEach(doc => {
                cloudEvents.push(doc.data());
            });

            // If cloud has documents, sync to local storage
            if (cloudEvents.length > 0) {
                localStorage.setItem('evenza_events', JSON.stringify(cloudEvents));
                notifyEventsUpdated(cloudEvents);
            } else {
                // If cloud is empty, check if we have local events to push to cloud
                const local = getStoredEvents();
                if (local.length > 0) {
                    console.log('[Evenza Cloud] Migrating local events to cloud Firestore...');
                    local.forEach(ev => {
                        db.collection('evenza_events').doc(String(ev.id)).set(ev).catch(console.error);
                    });
                } else {
                    localStorage.setItem('evenza_events', JSON.stringify([]));
                    notifyEventsUpdated([]);
                }
            }
        }, (error) => {
            console.error('[Evenza Cloud] Events sync listener error:', error);
            notifyStatusChange(false, error.message);
        });

        // 2. Listen for Registrations collection
        regsUnsubscribe = db.collection('evenza_registrations').onSnapshot((snapshot) => {
            const cloudRegs = [];
            snapshot.forEach(doc => {
                cloudRegs.push(doc.data());
            });

            if (cloudRegs.length > 0) {
                localStorage.setItem('evenza_registrations', JSON.stringify(cloudRegs));
                notifyRegsUpdated(cloudRegs);
            } else {
                const local = getStoredRegistrations();
                if (local.length > 0) {
                    console.log('[Evenza Cloud] Migrating local registrations to cloud Firestore...');
                    local.forEach(reg => {
                        db.collection('evenza_registrations').doc(String(reg.id)).set(reg).catch(console.error);
                    });
                } else {
                    localStorage.setItem('evenza_registrations', JSON.stringify([]));
                    notifyRegsUpdated([]);
                }
            }
        }, (error) => {
            console.error('[Evenza Cloud] Registrations sync listener error:', error);
        });
    }

    function notifyEventsUpdated(events) {
        eventListeners.forEach(fn => {
            try { fn(events); } catch (e) { console.error(e); }
        });
        window.dispatchEvent(new CustomEvent('evenza:eventsSync', { detail: { events } }));
    }

    function notifyRegsUpdated(regs) {
        regListeners.forEach(fn => {
            try { fn(regs); } catch (e) { console.error(e); }
        });
        window.dispatchEvent(new CustomEvent('evenza:regsSync', { detail: { regs } }));
    }

    // Helper functions for fallback local storage
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

    // Cloud CRUD operations
    async function saveEvent(eventObj) {
        // Update local storage immediately for fast UI feedback
        const local = getStoredEvents();
        const idx = local.findIndex(e => String(e.id) === String(eventObj.id));
        if (idx >= 0) {
            local[idx] = eventObj;
        } else {
            local.push(eventObj);
        }
        localStorage.setItem('evenza_events', JSON.stringify(local));

        // Sync with Cloud Firestore if available
        if (db && initialized) {
            try {
                await db.collection('evenza_events').doc(String(eventObj.id)).set(eventObj);
                console.log('[Evenza Cloud] Event saved to Firestore:', eventObj.id);
            } catch (err) {
                console.error('[Evenza Cloud] Error saving event to Firestore:', err);
                throw err;
            }
        }
        return eventObj;
    }

    async function deleteEvent(eventId) {
        // Update local storage
        const local = getStoredEvents().filter(e => String(e.id) !== String(eventId));
        localStorage.setItem('evenza_events', JSON.stringify(local));

        // Delete from Cloud
        if (db && initialized) {
            try {
                await db.collection('evenza_events').doc(String(eventId)).delete();
                console.log('[Evenza Cloud] Event deleted from Firestore:', eventId);
            } catch (err) {
                console.error('[Evenza Cloud] Error deleting event from Firestore:', err);
                throw err;
            }
        }
    }

    async function saveRegistration(regObj) {
        // Update local storage
        const local = getStoredRegistrations();
        const idx = local.findIndex(r => String(r.id) === String(regObj.id));
        if (idx >= 0) {
            local[idx] = regObj;
        } else {
            local.push(regObj);
        }
        localStorage.setItem('evenza_registrations', JSON.stringify(local));

        // Sync with Cloud
        if (db && initialized) {
            try {
                await db.collection('evenza_registrations').doc(String(regObj.id)).set(regObj);
                console.log('[Evenza Cloud] Registration saved to Firestore:', regObj.id);
            } catch (err) {
                console.error('[Evenza Cloud] Error saving registration to Firestore:', err);
                throw err;
            }
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

        if (db && initialized) {
            try {
                await db.collection('evenza_registrations').doc(String(regId)).update(patch);
                console.log('[Evenza Cloud] Registration updated in Firestore:', regId);
            } catch (err) {
                console.error('[Evenza Cloud] Error updating registration:', err);
                throw err;
            }
        }
        return item;
    }

    // Public API
    window.EvenzaCloud = {
        init,
        getConfig,
        saveConfig,
        isReady: () => initialized && db !== null,
        onEventsUpdated: (fn) => { if (typeof fn === 'function') eventListeners.push(fn); },
        onRegistrationsUpdated: (fn) => { if (typeof fn === 'function') regListeners.push(fn); },
        saveEvent,
        deleteEvent,
        saveRegistration,
        updateRegistration
    };

    // Auto-init on script load or DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(window);
