import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

// The key is only present when this host really runs a Reverb server, so its
// absence means "no realtime layer". Instantiating Echo without it throws
// inside pusher-js ("You must pass your app key"), which would stop the rest
// of the bundle from booting. Instead a no-op Echo is installed: Livewire's
// @echo listeners then mount cleanly (no "Laravel Echo cannot be found"),
// simply never receive anything, and the wire:poll fallback keeps the
// transcript current.
const key = import.meta.env.VITE_REVERB_APP_KEY;

if (key) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });
} else {
    const channel = {
        listen: () => channel,
        stopListening: () => channel,
        notification: () => channel,
        here: () => channel,
        joining: () => channel,
        leaving: () => channel,
    };

    window.Echo = {
        channel: () => channel,
        private: () => channel,
        encryptedPrivate: () => channel,
        join: () => channel,
        leave: () => {},
        socketId: () => null,
    };
}
