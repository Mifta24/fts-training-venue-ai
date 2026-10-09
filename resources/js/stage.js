/**
 * Full-screen stage chrome shared by the opening screen and every session:
 * rolls the projector screen up once the photo is ready, counts the session
 * display up or down to the session the guest chose, and pulls the screen down
 * again before following a link — so moving between pages feels like moving on
 * to the next session of a programme rather than loading a page.
 *
 * A link may carry `data-topic`: the question the guest picked from the agenda.
 * It is handed to the AI coordinator, which raises it once the next scene is up
 * (or straight away when the guest is already in that scene).
 */
const TOPIC_KEY = 'coordinator_pending_topic';
const SESSION_KEY = 'venue_last_session';
const SESSION_ORDER = ['01', '02', '03', '04', '05', '06'];

/**
 * The session display counts through every session between the last one the
 * guest was in and this one, so the programme visibly moves on.
 */
function runSessionCounter(stage, reducedMotion) {
    const current = stage.dataset.session;
    const code = stage.querySelector('[data-session-code]');
    let previous = null;

    try {
        previous = sessionStorage.getItem(SESSION_KEY);
        sessionStorage.setItem(SESSION_KEY, current);
    } catch { /* private mode: the display just shows the current session */ }

    if (!code || !previous || previous === current || reducedMotion) return;

    const from = SESSION_ORDER.indexOf(previous);
    const to = SESSION_ORDER.indexOf(current);
    if (from < 0 || to < 0) return;

    const step = to > from ? 1 : -1;
    let index = from;
    code.textContent = SESSION_ORDER[index];

    const timer = window.setInterval(() => {
        index += step;
        code.textContent = SESSION_ORDER[index];
        if (index === to) window.clearInterval(timer);
    }, 180);
}

function initStage() {
    const stage = document.querySelector('.stage');
    if (!stage) return;

    const loader = stage.querySelector('[data-stage-loader]');
    const image = stage.querySelector('.stage-image');
    const tour = stage.querySelector('[data-stage-tour]');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const showScene = () => loader?.classList.add('is-ready');

    runSessionCounter(stage, reducedMotion);

    // On phones the agenda is a scrolling strip; keep the current session in view.
    stage.querySelector('.agenda-item[aria-current]')?.scrollIntoView({ block: 'nearest', inline: 'center' });

    if (!image || image.complete) {
        showScene();
    } else {
        image.addEventListener('load', showScene, { once: true });
        image.addEventListener('error', showScene, { once: true });
    }

    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            leaving = false;
            stage.classList.remove('is-leaving');
            tour?.classList.remove('is-visible');
        }
    });

    let leaving = false;

    /** Pull the screen down, then take the guest over to `href`. */
    function leave(href, line = null, topic = null) {
        if (leaving) return;
        leaving = true;

        try {
            if (topic) sessionStorage.setItem(TOPIC_KEY, topic);
            else sessionStorage.removeItem(TOPIC_KEY);
        } catch { /* private mode: the scene still changes, only the topic is dropped */ }

        // The coordinator announces the session while the screen comes down.
        if (line && tour) {
            tour.textContent = line;
            tour.classList.add('is-visible');
        }

        stage.classList.add('is-leaving');

        const chime = window.venueSound?.isEnabled() ?? false;
        if (chime) window.venueSound.play('enter');

        const hold = reducedMotion ? 0 : line ? 900 : 520;
        window.setTimeout(() => { window.location.href = href; }, hold);
    }

    document.querySelectorAll('[data-stage-exit]').forEach((link) => {
        link.addEventListener('click', (event) => {
            const modified = event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0;
            if (event.defaultPrevented || modified || link.target === '_blank') return;

            event.preventDefault();

            // Already in this scene: no move needed, the coordinator just takes
            // the topic up where the guest is standing.
            const samePlace = link.pathname === window.location.pathname;
            if (samePlace && link.dataset.topic) {
                window.dispatchEvent(new CustomEvent('coordinator:ask', { detail: { message: link.dataset.topic } }));
                return;
            }
            if (samePlace) return;

            leave(link.href, link.dataset.tourLine, link.dataset.topic);
        });
    });

    window.venueStage = { leave };
}

/** The topic the guest picked on the previous scene, if any (read once). */
window.takePendingCoordinatorTopic = () => {
    try {
        const topic = sessionStorage.getItem(TOPIC_KEY);
        sessionStorage.removeItem(TOPIC_KEY);
        return topic;
    } catch {
        return null;
    }
};

document.addEventListener('DOMContentLoaded', initStage);
