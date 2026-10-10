(function () {
    'use strict';

    const cfg = window.UPLAR_TRACKING_CONFIG || {};
    if (!cfg.enabled || !cfg.endpoints || typeof window.fetch !== 'function') return;

    const nativeFetch = window.fetch.bind(window);
    const storageSession = 'uplar_tracking_session_id';
    const storageDevice = 'uplar_tracking_device_id';
    const maxBatchSize = 20;
    const flushDelayMs = 8000;
    const apiSummaryDelayMs = 20000;
    const heartbeatIntervalMs = 300000;
    const runtime = {
        sessionId: sessionStorage.getItem(storageSession) || '',
        deviceId: localStorage.getItem(storageDevice) || '',
        queue: [],
        apiTimings: new Map(),
        flushTimer: 0,
        apiTimer: 0,
        flushPromise: null,
        lastActivitySentAt: Date.now(),
        recentErrors: new Map(),
    };

    function uuid() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (char) {
            const random = Math.random() * 16 | 0;
            const value = char === 'x' ? random : ((random & 0x3) | 0x8);
            return value.toString(16);
        });
    }

    async function sha256(input) {
        if (!(window.crypto && window.crypto.subtle)) {
            let hash = 0;
            const text = String(input || '');
            for (let index = 0; index < text.length; index++) {
                hash = ((hash << 5) - hash) + text.charCodeAt(index);
                hash |= 0;
            }
            return String(hash);
        }
        const data = new TextEncoder().encode(String(input || ''));
        const buffer = await window.crypto.subtle.digest('SHA-256', data);
        return Array.from(new Uint8Array(buffer), byte => byte.toString(16).padStart(2, '0')).join('');
    }

    function fingerprint() {
        return {
            user_agent: navigator.userAgent || '',
            screen_resolution: window.screen ? `${window.screen.width}x${window.screen.height}` : '',
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || '',
            language: navigator.language || '',
            platform: navigator.platform || '',
        };
    }

    function basePayload() {
        return {
            csrf_token: cfg.csrfToken || '',
            session_id: runtime.sessionId || uuid(),
            page_url: location.pathname + location.search,
            referrer: document.referrer || '',
            page_title: document.title || '',
            page_type: cfg.pageName || '',
            fingerprint: fingerprint(),
        };
    }

    function updateSession(data) {
        if (!data || !data.ok || !data.session_id) return;
        runtime.sessionId = data.session_id;
        sessionStorage.setItem(storageSession, runtime.sessionId);
    }

    async function initDevice() {
        const current = fingerprint();
        runtime.deviceId = await sha256([
            current.user_agent,
            current.screen_resolution,
            current.timezone,
            current.language,
            current.platform,
        ].join('|'));
        localStorage.setItem(storageDevice, runtime.deviceId);
    }

    async function postJson(url, payload, useBeacon) {
        const body = JSON.stringify(payload);
        if (useBeacon && navigator.sendBeacon && body.length < 60000) {
            return {ok: navigator.sendBeacon(url, new Blob([body], {type: 'application/json'})), beacon: true};
        }
        const response = await nativeFetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body,
            keepalive: !!useBeacon,
        });
        let json = {};
        try { json = await response.json(); } catch (error) {}
        return Object.assign({ok: response.ok}, json);
    }

    async function touchSession(extra, useBeacon) {
        try {
            const data = await postJson(cfg.endpoints.session, Object.assign(basePayload(), extra || {}), !!useBeacon);
            updateSession(data);
            return data;
        } catch (error) {
            return {ok: false};
        }
    }

    function scheduleFlush(delay) {
        if (runtime.flushTimer) return;
        runtime.flushTimer = window.setTimeout(function () {
            runtime.flushTimer = 0;
            flushEvents(false);
        }, delay || flushDelayMs);
    }

    function enqueueEvent(name, metadata, category) {
        if (!name || !/^[a-z0-9:_-]+$/i.test(name)) return;
        if (runtime.queue.length >= 100) {
            const timingIndex = runtime.queue.findIndex(event => event.event_name === 'api_timing_summary');
            runtime.queue.splice(timingIndex >= 0 ? timingIndex : 0, 1);
        }
        runtime.queue.push({
            event_name: String(name).toLowerCase(),
            event_category: category || 'custom',
            metadata: metadata || {},
            request_id: uuid(),
        });
        if (runtime.queue.length >= maxBatchSize) flushEvents(false);
        else scheduleFlush();
    }

    function flushApiTimingSummaries() {
        if (runtime.apiTimer) {
            clearTimeout(runtime.apiTimer);
            runtime.apiTimer = 0;
        }
        runtime.apiTimings.forEach(function (stats, url) {
            enqueueEvent('api_timing_summary', {
                url,
                request_count: stats.count,
                average_ms: Math.round(stats.total / stats.count),
                min_ms: stats.min,
                max_ms: stats.max,
                error_count: stats.errors,
                last_status: stats.lastStatus,
            }, 'performance');
        });
        runtime.apiTimings.clear();
    }

    async function flushEvents(useBeacon) {
        if (runtime.flushTimer) {
            clearTimeout(runtime.flushTimer);
            runtime.flushTimer = 0;
        }
        if (runtime.flushPromise && !useBeacon) return runtime.flushPromise;
        if (!runtime.queue.length) return {ok: true, event_count: 0};

        const events = runtime.queue.splice(0, maxBatchSize);
        const payload = Object.assign(basePayload(), {events});
        const send = async function () {
            try {
                const result = await postJson(cfg.endpoints.event, payload, !!useBeacon);
                updateSession(result);
                if (!result.ok && !useBeacon) runtime.queue.unshift(...events);
                return result;
            } catch (error) {
                if (!useBeacon) runtime.queue.unshift(...events);
                return {ok: false};
            } finally {
                runtime.flushPromise = null;
                if (runtime.queue.length && !useBeacon) scheduleFlush(1000);
            }
        };
        runtime.flushPromise = send();
        return runtime.flushPromise;
    }

    function trackEvent(name, metadata, category) {
        enqueueEvent(name, metadata, category);
        return Promise.resolve({ok: true, queued: true});
    }

    function errorKey(payload) {
        return [payload.message || '', payload.file_url || '', payload.line_number || '', payload.column_number || ''].join('|');
    }

    async function trackError(errorPayload) {
        const payload = Object.assign(basePayload(), errorPayload || {});
        const key = errorKey(payload);
        const now = Date.now();
        if (runtime.recentErrors.has(key) && now - runtime.recentErrors.get(key) < 60000) {
            return {ok: true, deduplicated: true};
        }
        runtime.recentErrors.set(key, now);
        if (runtime.recentErrors.size > 50) {
            const oldest = runtime.recentErrors.keys().next().value;
            runtime.recentErrors.delete(oldest);
        }
        try {
            const data = await postJson(cfg.endpoints.error, payload, true);
            updateSession(data);
            return data;
        } catch (error) {
            return {ok: false};
        }
    }

    function scheduleActivity(reason) {
        const now = Date.now();
        if (document.visibilityState === 'hidden' || now - runtime.lastActivitySentAt < heartbeatIntervalMs) return;
        runtime.lastActivitySentAt = now;
        if (runtime.queue.length) {
            flushEvents(false);
            return;
        }
        touchSession({metadata: {activity_reason: reason || 'heartbeat'}}, false);
    }

    function pageLoadMetadata() {
        const entry = performance.getEntriesByType && performance.getEntriesByType('navigation')[0];
        return {
            load_time_ms: entry ? Math.round(entry.loadEventEnd || performance.now()) : Math.round(performance.now()),
            dom_content_loaded_ms: entry ? Math.round(entry.domContentLoadedEventEnd) : null,
            type: entry ? entry.type : 'navigate',
        };
    }

    function isTrackingRequest(rawUrl) {
        try {
            const url = new URL(typeof rawUrl === 'string' ? rawUrl : rawUrl.url, location.href);
            return url.origin === location.origin && /(?:^|\/)track\/(?:event|session|error|log|dashboard)\.php$/i.test(url.pathname);
        } catch (error) {
            return false;
        }
    }

    function normalizeRequestUrl(rawUrl) {
        try {
            const url = new URL(typeof rawUrl === 'string' ? rawUrl : rawUrl.url, location.href);
            if (url.origin !== location.origin) return '';
            return url.pathname.replace(/^\/GG\//, '') + url.search;
        } catch (error) {
            return '';
        }
    }

    function recordApiTiming(url, duration, response) {
        if (!url) return;
        const current = runtime.apiTimings.get(url) || {count: 0, total: 0, min: duration, max: duration, errors: 0, lastStatus: 0};
        current.count++;
        current.total += duration;
        current.min = Math.min(current.min, duration);
        current.max = Math.max(current.max, duration);
        current.lastStatus = response ? response.status : 0;
        if (!response || !response.ok) current.errors++;
        runtime.apiTimings.set(url, current);
        if (!runtime.apiTimer) {
            runtime.apiTimer = window.setTimeout(flushApiTimingSummaries, apiSummaryDelayMs);
        }
    }

    function instrumentFetch() {
        window.fetch = async function () {
            const args = arguments;
            const request = args[0];
            if (isTrackingRequest(request)) return nativeFetch.apply(window, args);
            const startedAt = performance.now();
            let response;
            try {
                response = await nativeFetch.apply(window, args);
                return response;
            } finally {
                recordApiTiming(normalizeRequestUrl(request), Math.round(performance.now() - startedAt), response);
            }
        };
    }

    function bindTrackedClicks() {
        document.addEventListener('click', function (event) {
            const target = event.target.closest('[data-track-event]');
            if (!target) return;
            trackEvent(target.getAttribute('data-track-event'), {
                label: target.getAttribute('data-track-label') || target.textContent.trim().slice(0, 120),
                href: target.getAttribute('href') || '',
            }, 'interaction');
        }, {passive: true});
    }

    function bindErrors() {
        window.addEventListener('error', function (event) {
            trackError({
                source: 'frontend', severity: 'error', message: event.message || 'Erro JavaScript',
                file_url: event.filename || location.pathname, line_number: event.lineno || null,
                column_number: event.colno || null, stack_trace: event.error && event.error.stack ? event.error.stack : '',
                context: {page: location.pathname},
            });
        });
        window.addEventListener('unhandledrejection', function (event) {
            const reason = event.reason || {};
            trackError({
                source: 'frontend', severity: 'error', message: reason.message || String(reason),
                file_url: location.pathname, stack_trace: reason.stack || '', context: {type: 'unhandledrejection'},
            });
        });
    }

    async function boot() {
        await initDevice();
        instrumentFetch();
        bindTrackedClicks();
        bindErrors();
        enqueueEvent(cfg.pageEvent || 'page_view', {page_name: cfg.pageName || '', user_id: cfg.userId || null}, 'navigation');
        enqueueEvent('page_load', pageLoadMetadata(), 'performance');
        await flushEvents(false);

        ['pointerdown', 'keydown'].forEach(function (eventName) {
            document.addEventListener(eventName, function () { scheduleActivity(eventName); }, {passive: true});
        });
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'hidden') {
                flushApiTimingSummaries();
                flushEvents(true);
            } else {
                scheduleActivity('visibilitychange');
            }
        });
        window.addEventListener('pagehide', function () {
            flushApiTimingSummaries();
            flushEvents(true);
        });
    }

    window.UplarTracker = {
        trackEvent,
        trackError,
        touchSession,
        flush: function () { flushApiTimingSummaries(); return flushEvents(false); },
        getSessionId: function () { return runtime.sessionId; },
        getDeviceId: function () { return runtime.deviceId; },
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, {once: true});
    else boot();
}());
