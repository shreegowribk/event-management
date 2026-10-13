// Evenza PWA Integration Script
(function () {
    // 1. Register Service Worker
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js', { scope: '/' })
                .then((reg) => {
                    console.log('[Evenza PWA] Service Worker registered with scope:', reg.scope);
                })
                .catch((err) => {
                    console.warn('[Evenza PWA] Service Worker registration failed:', err);
                });
        });
    }

    // 2. Install Prompt Management
    let deferredPrompt = null;
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

    if (isStandalone) {
        document.body && document.body.classList.add('evenza-pwa-standalone');
        return; // Already installed and running as an app
    }

    const isIos = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;

    function createInstallUI() {
        if (document.getElementById('evenza-pwa-install-banner')) return;

        const banner = document.createElement('div');
        banner.id = 'evenza-pwa-install-banner';
        banner.innerHTML = `
            <div class="evenza-pwa-container">
                <div class="evenza-pwa-icon">
                    <img src="/icons/icon-192.png" alt="Evenza" width="42" height="42" style="border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.25);" />
                </div>
                <div class="evenza-pwa-text">
                    <div class="evenza-pwa-title">Install Evenza App</div>
                    <div class="evenza-pwa-desc">Instant access & live ticket tracking</div>
                </div>
                <div class="evenza-pwa-actions">
                    <button id="evenza-pwa-install-btn" class="evenza-pwa-btn-primary">
                        <i class="bi bi-download me-1"></i> Install
                    </button>
                    <button id="evenza-pwa-close-btn" class="evenza-pwa-btn-close" aria-label="Close">&times;</button>
                </div>
            </div>
        `;

        // Inject CSS styles for the PWA banner
        const style = document.createElement('style');
        style.textContent = `
            #evenza-pwa-install-banner {
                position: fixed;
                bottom: 24px;
                left: 50%;
                transform: translateX(-50%) translateY(120%);
                z-index: 99999;
                width: calc(100% - 32px);
                max-width: 440px;
                background: rgba(15, 23, 42, 0.94);
                backdrop-filter: blur(16px);
                -webkit-backdrop-filter: blur(16px);
                border: 1px solid rgba(255, 255, 255, 0.12);
                border-radius: 20px;
                padding: 12px 16px;
                box-shadow: 0 20px 45px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(79, 70, 229, 0.25);
                transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
                color: #ffffff;
                font-family: inherit;
            }
            #evenza-pwa-install-banner.evenza-pwa-show {
                transform: translateX(-50%) translateY(0);
            }
            .evenza-pwa-container {
                display: flex;
                align-items: center;
                gap: 12px;
            }
            .evenza-pwa-text {
                flex: 1;
                min-width: 0;
            }
            .evenza-pwa-title {
                font-weight: 700;
                font-size: 0.95rem;
                color: #ffffff;
                line-height: 1.2;
            }
            .evenza-pwa-desc {
                font-size: 0.78rem;
                color: rgba(255, 255, 255, 0.65);
                margin-top: 2px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .evenza-pwa-actions {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .evenza-pwa-btn-primary {
                background: linear-gradient(135deg, #4f46e5, #7c3aed);
                color: #ffffff;
                border: none;
                border-radius: 12px;
                padding: 8px 16px;
                font-size: 0.86rem;
                font-weight: 600;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                box-shadow: 0 4px 14px rgba(79, 70, 229, 0.4);
                transition: transform 0.15s ease, filter 0.15s ease;
            }
            .evenza-pwa-btn-primary:active {
                transform: scale(0.96);
            }
            .evenza-pwa-btn-close {
                background: transparent;
                border: none;
                color: rgba(255, 255, 255, 0.5);
                font-size: 1.4rem;
                line-height: 1;
                cursor: pointer;
                padding: 4px;
            }
            .evenza-pwa-btn-close:hover {
                color: #ffffff;
            }
            @media (max-width: 480px) {
                #evenza-pwa-install-banner {
                    bottom: 16px;
                    width: calc(100% - 24px);
                    padding: 10px 14px;
                }
            }
        `;
        document.head.appendChild(style);
        document.body.appendChild(banner);

        // Event listeners
        const installBtn = document.getElementById('evenza-pwa-install-btn');
        const closeBtn = document.getElementById('evenza-pwa-close-btn');

        installBtn.addEventListener('click', async () => {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                const choice = await deferredPrompt.userChoice;
                if (choice.outcome === 'accepted') {
                    console.log('[Evenza PWA] User accepted install prompt');
                }
                deferredPrompt = null;
                hideBanner();
            } else if (isIos) {
                alert('To install Evenza on iPhone/iPad:\n1. Tap the Share button at the bottom of Safari\n2. Select "Add to Home Screen"');
            }
        });

        closeBtn.addEventListener('click', () => {
            hideBanner();
            sessionStorage.setItem('evenza_pwa_banner_dismissed', 'true');
        });

        function hideBanner() {
            banner.classList.remove('evenza-pwa-show');
        }
    }

    function showBanner() {
        if (sessionStorage.getItem('evenza_pwa_banner_dismissed')) return;
        createInstallUI();
        const banner = document.getElementById('evenza-pwa-install-banner');
        if (banner) {
            setTimeout(() => {
                banner.classList.add('evenza-pwa-show');
            }, 1200);
        }
    }

    // Android / Chrome beforeinstallprompt event
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        showBanner();
    });

    // iOS prompt (only if in mobile safari and not standalone)
    if (isIos && !isStandalone && !sessionStorage.getItem('evenza_pwa_banner_dismissed')) {
        window.addEventListener('load', () => {
            setTimeout(showBanner, 2000);
        });
    }

    window.addEventListener('appinstalled', () => {
        console.log('[Evenza PWA] Application installed successfully');
        const banner = document.getElementById('evenza-pwa-install-banner');
        if (banner) banner.remove();
    });
})();
