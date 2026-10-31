/*
  Public site behaviour.
  ----------------------
  No framework and no dependencies: every behaviour is a small, independent
  initialiser that looks for its own data attributes and does nothing when the
  element is absent. That keeps the page cost flat as sections are added or
  removed from the admin.
*/

const prefersReducedMotion = () =>
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const motionEnabled = () =>
    !prefersReducedMotion() &&
    !document.body.classList.contains('site-animation-none');

/**
 * Reveal sections and cards as they enter the viewport.
 *
 * The reveal is re-armable: the class tracks the element as it moves in and out
 * of the viewport rather than being written once and dropped. That is what lets
 * a visitor scroll back to the top and ride the animations down again instead
 * of finding every section already parked visible, which is what the old
 * one-shot `unobserve()` produced.
 */
function initReveal() {
    const showAll = () => {
        document
            .querySelectorAll('[data-reveal]')
            .forEach((element) => element.classList.add('is-inview'));
    };

    const animate = 'IntersectionObserver' in window && motionEnabled();

    if (!animate) {
        showAll();

        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                entry.target.classList.toggle('is-inview', entry.isIntersecting);
            });
        },
        { rootMargin: '0px 0px -12% 0px', threshold: 0.12 },
    );

    const register = (element) => {
        if (element.dataset.revealReady === 'true') {
            return;
        }

        element.dataset.revealReady = 'true';

        // Anything already on screen when it is registered appears immediately
        // so the first paint is never empty, but it stays observed so it can
        // reset and replay once the visitor scrolls away and back.
        const rect = element.getBoundingClientRect();

        if (rect.top < window.innerHeight * 0.9 && rect.bottom > 0) {
            element.classList.add('is-inview');
        }

        observer.observe(element);
    };

    document.querySelectorAll('[data-reveal]').forEach(register);

    // Blocks, form results and anything else swapped in after load would
    // otherwise stay at `opacity: 0`, which is exactly what happened to the
    // contact form's success message. Watch the DOM and register them too.
    new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType !== Node.ELEMENT_NODE) {
                    return;
                }

                if (node.matches('[data-reveal]')) {
                    register(node);
                }

                node.querySelectorAll?.('[data-reveal-group]').forEach(stagger);
                node.querySelectorAll?.('[data-reveal]').forEach(register);
            });
        });
    }).observe(document.body, { childList: true, subtree: true });
}

/** Stagger direct children inside a reveal group. */
function stagger(group) {
    Array.from(group.children).forEach((child, index) => {
        child.style.setProperty('--child-index', String(index));
    });
}

function initRevealGroups() {
    document.querySelectorAll('[data-reveal-group]').forEach(stagger);
}

/** Count a number up to its final value the first time it is seen. */
function initCounters() {
    const counters = document.querySelectorAll('[data-count]');

    if (counters.length === 0) {
        return;
    }

    const animate = (element) => {
        const target = Number(element.dataset.count || 0);
        const suffix = element.dataset.countSuffix || '';
        const prefix = element.dataset.countPrefix || '';
        const duration = Number(element.dataset.countDuration || 1400);

        if (!Number.isFinite(target) || !motionEnabled()) {
            element.textContent = prefix + target + suffix;
            return;
        }

        const start = performance.now();

        const frame = (now) => {
            const progress = Math.min((now - start) / duration, 1);
            // Ease-out so the number decelerates into its final value.
            const eased = 1 - Math.pow(1 - progress, 3);

            element.textContent = prefix + Math.round(target * eased) + suffix;

            if (progress < 1) {
                requestAnimationFrame(frame);
            }
        };

        requestAnimationFrame(frame);
    };

    if (!('IntersectionObserver' in window)) {
        counters.forEach(animate);
        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }

                animate(entry.target);
                observer.unobserve(entry.target);
            });
        },
        { threshold: 0.4 },
    );

    counters.forEach((counter) => observer.observe(counter));
}

let headerMenusBound = false;

/** Shut every header dropdown except the one being opened. */
function closeDropdowns(except) {
    document.querySelectorAll('[data-dropdown-menu]').forEach((menu) => {
        if (menu === except) {
            return;
        }

        menu.classList.add('hidden');
        menu.parentElement
            ?.querySelector('[data-dropdown-toggle]')
            ?.setAttribute('aria-expanded', 'false');
    });
}

/** Open or shut the mobile navigation panel, icons and label included. */
function setMobileNav(open) {
    const toggle = document.querySelector('[data-mobile-toggle]');
    const panel = document.querySelector('[data-mobile-nav]');

    if (!toggle || !panel) {
        return;
    }

    panel.classList.toggle('hidden', !open);
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    toggle.setAttribute('aria-label', open ? toggle.dataset.labelClose : toggle.dataset.labelOpen);
    toggle.querySelector('[data-mobile-icon-open]')?.classList.toggle('hidden', open);
    toggle
        .querySelector('[data-mobile-icon-close]')
        ?.classList.toggle('hidden', !open);
}

/**
 * Header menus: one dropdown open at a time, plus the mobile panel.
 *
 * The listeners sit on `document` and are installed once. Binding per element
 * meant a second run of `boot()` stacked a second handler, and opening a menu
 * immediately closed it again — a click that looked like it did nothing.
 */
function initHeaderMenus() {
    if (headerMenusBound) {
        return;
    }

    headerMenusBound = true;

    document.addEventListener('click', (event) => {
        const target = event.target;

        if (!(target instanceof Element)) {
            return;
        }

        const toggle = target.closest('[data-dropdown-toggle]');

        if (toggle) {
            event.preventDefault();

            const menu = toggle.parentElement?.querySelector('[data-dropdown-menu]');

            if (!menu) {
                return;
            }

            const opening = menu.classList.contains('hidden');

            closeDropdowns(opening ? menu : null);
            menu.classList.toggle('hidden', !opening);
            toggle.setAttribute('aria-expanded', opening ? 'true' : 'false');

            return;
        }

        if (target.closest('[data-mobile-toggle]')) {
            event.preventDefault();
            setMobileNav(
                document
                    .querySelector('[data-mobile-nav]')
                    ?.classList.contains('hidden') ?? false,
            );

            return;
        }

        // Following a link in the panel closes it, so the next page is not
        // covered by a menu the visitor has already finished with.
        if (target.closest('[data-mobile-nav] a')) {
            setMobileNav(false);
            return;
        }

        if (!target.closest('[data-dropdown]')) {
            closeDropdowns(null);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        closeDropdowns(null);
        setMobileNav(false);
    });

    // The desktop navigation takes over at `xl`; the panel must not stay open
    // underneath it after a rotation or a resized window.
    window.addEventListener(
        'resize',
        () => {
            if (window.innerWidth >= 1280) {
                setMobileNav(false);
            }
        },
        { passive: true },
    );
}

/** Shadow on the sticky header once the page has moved. */
function initStickyHeader() {
    const header = document.querySelector('[data-site-header]');

    if (!header) {
        return;
    }

    const update = () => {
        header.classList.toggle('is-scrolled', window.scrollY > 12);
    };

    update();
    window.addEventListener('scroll', update, { passive: true });
}

/** FAQ accordion: a button owns the panel and reports its own state. */
function initAccordion() {
    document.querySelectorAll('[data-faq]').forEach((item) => {
        const button = item.querySelector('[data-faq-toggle]');
        const panel = item.querySelector('.faq-panel');

        if (!button || !panel) {
            return;
        }

        button.addEventListener('click', () => {
            const open = !item.classList.contains('is-open');

            item.classList.toggle('is-open', open);
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
            panel.setAttribute('aria-hidden', open ? 'false' : 'true');
        });
    });
}

/**
 * Hero slider used by the banner layout: cross-fades images and keeps the
 * caption in step. It pauses when the tab is hidden.
 */
function initSlider() {
    const slider = document.querySelector('[data-slider]');

    if (!slider) {
        return;
    }

    const slides = Array.from(slider.querySelectorAll('[data-slide]'));
    const dots = Array.from(slider.querySelectorAll('[data-slide-dot]'));
    const previous = slider.querySelector('[data-slide-prev]');
    const next = slider.querySelector('[data-slide-next]');
    const progress = slider.querySelector('[data-slide-progress]');

    if (slides.length < 2) {
        return;
    }

    const interval = Number(slider.dataset.sliderInterval || 6000);
    let active = 0;
    let timer = null;

    const show = (index) => {
        active = (index + slides.length) % slides.length;

        slides.forEach((slide, position) => {
            slide.classList.toggle('is-active', position === active);
        });

        dots.forEach((dot, position) => {
            dot.classList.toggle('is-active', position === active);
            dot.setAttribute('aria-current', position === active ? 'true' : 'false');
        });

        if (progress) {
            progress.style.animation = 'none';
            // Force a reflow so the progress bar restarts on every slide.
            void progress.offsetWidth;
            progress.style.animation = '';
        }
    };

    const start = () => {
        if (!motionEnabled()) {
            return;
        }

        stop();
        timer = window.setInterval(() => show(active + 1), interval);
    };

    const stop = () => {
        if (timer !== null) {
            window.clearInterval(timer);
            timer = null;
        }
    };

    show(0);
    start();

    previous?.addEventListener('click', () => {
        show(active - 1);
        start();
    });

    next?.addEventListener('click', () => {
        show(active + 1);
        start();
    });

    dots.forEach((dot, index) => {
        dot.addEventListener('click', () => {
            show(index);
            start();
        });
    });

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stop();
        } else {
            start();
        }
    });
}

/**
 * Farm journey: every year is a card of its own, and the tractor drives the
 * road by itself — pausing at each year in turn, then riding on. The tour sets
 * off with the page rather than waiting to be scrolled into sight, so the
 * tractor is already on the road by the time the visitor reaches the section.
 * Hovering it, tabbing into it or switching the animations off holds it parked,
 * and pressing a card drives straight to that year.
 */
function initFarmJourney() {
    document.querySelectorAll('[data-journey]').forEach((journey) => {
        const stops = Array.from(journey.querySelectorAll('[data-journey-stop]'));
        const tractor = journey.querySelector('[data-journey-tractor]');
        const live = journey.querySelector('[data-journey-live]');
        const track = journey.querySelector('[data-journey-track]');
        const road = journey.querySelector('[data-journey-road-path]');
        const centre = journey.querySelector('[data-journey-road-centre]');
        const roadSvg = journey.querySelector('[data-journey-road]');

        if (stops.length === 0 || !track || !road || !roadSvg) {
            return;
        }

        // A Livewire re-render can hand back the same element. Binding twice
        // would leave two timers pulling the tractor in different directions.
        if (journey.dataset.journeyReady === 'true') {
            return;
        }

        journey.dataset.journeyReady = 'true';
        // The dimming of the years the tractor has not reached only starts here,
        // so a visitor without JavaScript sees every card at full strength.
        journey.classList.add('is-armed');

        const interval = Number(journey.dataset.journeyInterval) || 4200;

        let index = 0;
        let ticking = null;
        let resting = null;
        let driving = null;
        let rideId = 0;
        let heading = null;
        let held = false;
        let anchors = [];
        let pathLength = 0;
        let cursor = 0;

        // Closest point on the road to a pixel, walking forwards from `from`
        // so that stops never overtake each other.
        const nearestLength = (point, from = 0) => {
            let best = from;
            let bestDist = Number.POSITIVE_INFINITY;

            for (let at = from; at <= pathLength; at += 2) {
                const p = road.getPointAtLength(at);
                const dist = (p.x - point.x) ** 2 + (p.y - point.y) ** 2;

                if (dist < bestDist) {
                    bestDist = dist;
                    best = at;
                }
            }

            return best;
        };

        // The road joins the dot of every card, in order, so it zigzags
        // through the two-column grid exactly the way the tractor drives it.
        const measure = () => {
            // A card that has not been revealed yet is still translated and
            // scaled down on screen. Reading those painted rectangles would
            // draw the road to where the cards hover mid-reveal instead of
            // where they rest, so the section is frozen while it is measured —
            // which is also what lets the tour start before it is on screen.
            journey.classList.add('is-measuring');

            const box = track.getBoundingClientRect();

            if (box.width < 2 || box.height < 2) {
                journey.classList.remove('is-measuring');

                return;
            }

            const points = stops.map((stop) => {
                const dot = stop.querySelector('.farm-journey__dot') || stop;
                const rect = dot.getBoundingClientRect();

                return {
                    x: rect.left - box.left + rect.width / 2,
                    y: rect.top - box.top + rect.height / 2,
                };
            });

            // Everything below works from those numbers and the SVG path, so
            // the cards are free to animate again before the next paint.
            journey.classList.remove('is-measuring');

            roadSvg.setAttribute('viewBox', `0 0 ${box.width} ${box.height}`);

            let d = `M ${points[0].x.toFixed(1)} ${points[0].y.toFixed(1)}`;

            for (let i = 1; i < points.length; i++) {
                const a = points[i - 1];
                const b = points[i];
                const dx = b.x - a.x;
                // Horizontal runs stay flat; row changes bend gently.
                d += ` C ${(a.x + dx * 0.45).toFixed(1)} ${a.y.toFixed(1)}, ${(b.x - dx * 0.45).toFixed(1)} ${b.y.toFixed(1)}, ${b.x.toFixed(1)} ${b.y.toFixed(1)}`;
            }

            road.setAttribute('d', d);

            if (centre) {
                centre.setAttribute('d', d);
            }

            pathLength = road.getTotalLength();

            // Where each year sits along the road: walk forwards from the
            // previous stop, so two nearby dots never swap places.
            anchors = [];

            let from = 0;

            points.forEach((point) => {
                const best = nearestLength(point, from);

                anchors.push(best);
                from = best;
            });

            if (heading !== null) {
                // The road was redrawn mid-journey (a reveal or a resize moved
                // the cards). Pick the ride up from the pixel the tractor is
                // standing on and carry on to the same year — never a snap.
                const target = heading;
                const where = tractor.getBoundingClientRect();

                drive(
                    anchors[target] ?? 0,
                    () => arriveAt(target),
                    {
                        from: nearestLength({
                            x: where.left - box.left + where.width / 2,
                            y: where.top - box.top + where.height / 2,
                        }),
                    },
                );

                return;
            }

            cursor = anchors[index] ?? 0;
            place(cursor);
        };

        const place = (offset, { rolling = 1 } = {}) => {
            if (!tractor || pathLength === 0) {
                return;
            }

            const p = road.getPointAtLength(offset);
            const ahead = road.getPointAtLength(Math.min(pathLength, offset + 6 * rolling));
            const behind = road.getPointAtLength(Math.max(0, offset - 6 * rolling));

            // The tractor's wheels sit on the tarmac and its nose points the
            // way it travels — so on the return trip it runs along the road
            // backwards, exactly like a real tractor shifting into reverse.
            if (Math.abs(ahead.x - behind.x) > 1) {
                tractor.classList.toggle('is-facing-left', ahead.x < behind.x);
            }

            tractor.style.transform = `translate(${(p.x - tractor.offsetWidth / 2).toFixed(1)}px, ${(p.y - tractor.offsetHeight + 10).toFixed(1)}px)`;
        };

        // The tractor is never teleported: it covers every metre of road
        // between two years, from wherever it already sits. Duration grows with
        // distance, so a column-to-column hop and the long return trip to the
        // first year both read as one continuous drive.
        const speed = (distance) => Math.max(800, Math.min(2200, (distance / 420) * 1000));

        const drive = (to, onArrive = null, { from = cursor } = {}) => {
            if (driving !== null) {
                cancelAnimationFrame(driving);
                driving = null;
            }

            cursor = from;

            // Each ride is numbered, so an interrupted trip cannot announce
            // its own destination after a newer one has already taken over.
            const ride = ++rideId;

            const finish = () => {
                if (ride !== rideId) {
                    return;
                }

                tractor.classList.remove('is-reversing');
                resting = window.setTimeout(
                    () => tractor.classList.remove('is-moving'),
                    250,
                );

                if (onArrive) {
                    onArrive();
                }
            };

            const delta = to - cursor;

            if (Math.abs(delta) < 1 || !motionEnabled()) {
                cursor = to;
                place(to);
                finish();

                return;
            }

            // Negative delta means the return trip: sample the road in travel
            // direction, so the nose points backwards and the wheels spin off.
            const rolling = Math.sign(delta);
            const origin = cursor;
            const duration = speed(Math.abs(delta));
            const startedAt = performance.now();

            window.clearTimeout(resting);
            tractor.classList.add('is-moving');
            tractor.classList.toggle('is-reversing', rolling < 0);

            const frame = (now) => {
                if (ride !== rideId) {
                    return;
                }

                const t = Math.min(1, (now - startedAt) / duration);
                // Cubic ease keeps a steady roll through the middle of the
                // hop, with a soft pull-away and a gentle park at the year.
                const progress = t < 0.5 ? 4 * t * t * t : 1 - (-2 * t + 2) ** 3 / 2;

                cursor = origin + delta * progress;
                place(cursor, { rolling });

                if (t < 1) {
                    driving = requestAnimationFrame(frame);
                } else {
                    driving = null;
                    cursor = to;
                    place(to, { rolling });
                    finish();
                }
            };

            driving = requestAnimationFrame(frame);
        };

        // Only the year the tractor is actually parked on gets the highlight,
        // so a card never lights up a full second before the ride arrives.
        const settle = (active) => {
            heading = null;

            stops.forEach((stop, position) => {
                stop.classList.toggle('is-active', position === active);
                stop.classList.toggle('is-past', position < active);
                stop.classList.remove('is-coming');
            });
        };

        const announce = (position) => {
            if (!live) {
                return;
            }

            const year = stops[position].dataset.year || '';
            const text = stops[position].dataset.text || '';
            live.textContent = year ? `${year}: ${text}` : text;
        };

        // The single place a ride ends: the year lights up, the tour announces
        // it, and the road knows nothing is in flight any more.
        const arriveAt = (target) => {
            index = target;
            settle(target);
            announce(target);
        };

        const goTo = (next, { via = 'drive' } = {}) => {
            const target = Math.min(stops.length - 1, Math.max(0, next));
            const anchor = anchors[target] ?? 0;

            index = target;

            if (anchor === 0 && pathLength === 0) {
                // Nothing has been measured yet, because the track had no size
                // to read; the observers below re-measure and park it anyway.
                arriveAt(target);

                return;
            }

            if (via === 'jump') {
                // A click on a card across the page arrives where the finger
                // pointed; the next automatic hop still drives from here.
                cursor = anchor;
                place(anchor);
                arriveAt(target);

                return;
            }

            heading = target;

            stops.forEach((stop, position) => {
                stop.classList.remove('is-active');
                stop.classList.toggle('is-coming', position === target);
            });

            drive(anchor, () => arriveAt(target));
        };

        // The tour only ever rolls forwards along the road: each year drives
        // into the next, and when the last year is reached the tractor makes
        // one smooth return trip back down the road to 2003. Never a jump.
        const step = () => {
            if (index + 1 >= stops.length) {
                goTo(0, { via: 'back' });

                return;
            }

            goTo(index + 1);
        };

        const start = () => {
            if (
                ticking !== null ||
                stops.length < 2 ||
                held ||
                !motionEnabled()
            ) {
                return;
            }

            ticking = window.setInterval(step, interval);
        };

        const stop = () => {
            if (ticking === null) {
                return;
            }

            window.clearInterval(ticking);
            ticking = null;
        };

        stops.forEach((stop) => {
            const card = stop.querySelector('[data-journey-jump]');

            if (!card) {
                return;
            }

            const position = stops.indexOf(stop);

            card.addEventListener('click', () => goTo(position, { via: 'jump' }));
            card.addEventListener('focus', () => goTo(position));
        });

        const hold = () => {
            held = true;
            stop();
        };

        const release = () => {
            held = false;
            start();
        };

        journey.addEventListener('mouseenter', hold);
        journey.addEventListener('mouseleave', release);
        journey.addEventListener('focusin', hold);
        journey.addEventListener('focusout', release);

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                stop();
            } else {
                start();
            }
        });

        // A window resize reshuffles the grid, so the road is redrawn to
        // match and the tractor parks back on the current year.
        let resizing = null;

        const remeasure = () => {
            window.clearTimeout(resizing);
            resizing = window.setTimeout(measure, 150);
        };

        window.addEventListener(
            'resize',
            remeasure,
            { passive: true },
        );

        // Reveals, late font loads and grid rewraps change the track without
        // resizing the window. Redraw the road the moment its box settles,
        // or the tractor would drive last frame's layout and land off a year.
        if ('ResizeObserver' in window) {
            new ResizeObserver(remeasure).observe(track);
        }

        measure();
        goTo(0);
        start();

        // The interval above would leave the tractor parked on the first year
        // for a whole 4200ms before it set off, which reads as "not moving" on
        // first load. Drive the first hop straight away; the interval then
        // paces the rest of the journey.
        if (motionEnabled() && stops.length > 1) {
            step();
        }

        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(() => measure());
        }
    });
}

/**
 * Testimonial carousel.
 *
 * Two cards are on screen; every few seconds the track slides one card along
 * and wraps to the start. The step is measured from the DOM rather than assumed
 * from the configured count, because phones always show a single card — with a
 * fixed step the track would stop two cards short of its end there.
 */
function initCarousel() {
    document.querySelectorAll('[data-carousel]').forEach((carousel) => {
        // A Livewire morph can hand back the same element on a second boot;
        // binding twice would leave two timers fighting over the track.
        if (carousel.dataset.carouselReady === 'true') {
            return;
        }

        const track = carousel.querySelector('[data-carousel-track]');
        const slides = Array.from(carousel.querySelectorAll('[data-carousel-slide]'));
        const dots = carousel.querySelector('[data-carousel-dots]');

        if (!track || slides.length < 2) {
            return;
        }

        carousel.dataset.carouselReady = 'true';

        const interval = Number(carousel.dataset.carouselInterval || 2000);
        let active = 0;
        let timer = null;
        let dotButtons = [];

        const measure = () => {
            const style = window.getComputedStyle(track);
            const gap = parseFloat(style.columnGap || style.gap || '0') || 0;
            const width = slides[0].getBoundingClientRect().width;
            const viewport = track.parentElement.getBoundingClientRect().width;
            const perView = Math.max(
                1,
                Math.min(slides.length, Math.round((viewport + gap) / (width + gap))),
            );

            return {
                step: width + gap,
                perView,
                last: Math.max(0, slides.length - perView),
            };
        };

        const paintDots = (last) => {
            if (!dots) {
                return;
            }

            if (dotButtons.length !== last + 1) {
                dots.innerHTML = '';
                dotButtons = [];

                for (let index = 0; index <= last; index += 1) {
                    const dot = document.createElement('button');
                    dot.type = 'button';
                    dot.className = 'carousel-dot';
                    dot.setAttribute('aria-label', `${index + 1}`);
                    dot.addEventListener('click', () => {
                        show(index);
                        start();
                    });
                    dots.appendChild(dot);
                    dotButtons.push(dot);
                }
            }

            dotButtons.forEach((dot, index) => {
                dot.classList.toggle('is-active', index === active);
                dot.setAttribute('aria-current', index === active ? 'true' : 'false');
            });
        };

        const show = (index) => {
            const { step, last } = measure();
            active = index < 0 ? last : index > last ? 0 : index;

            track.style.transform = `translate3d(${-active * step}px, 0, 0)`;
            paintDots(last);
        };

        const stop = () => {
            if (timer !== null) {
                window.clearInterval(timer);
                timer = null;
            }
        };

        const start = () => {
            stop();

            if (!motionEnabled() || interval <= 0) {
                return;
            }

            timer = window.setInterval(() => show(active + 1), interval);
        };

        carousel.querySelector('[data-carousel-prev]')?.addEventListener('click', () => {
            show(active - 1);
            start();
        });

        carousel.querySelector('[data-carousel-next]')?.addEventListener('click', () => {
            show(active + 1);
            start();
        });

        // Reading a quote takes longer than two seconds, so hovering or tabbing
        // into the carousel holds it still.
        carousel.addEventListener('mouseenter', stop);
        carousel.addEventListener('mouseleave', start);
        carousel.addEventListener('focusin', stop);
        carousel.addEventListener('focusout', start);

        document.addEventListener('visibilitychange', () => {
            document.hidden ? stop() : start();
        });

        window.addEventListener('resize', () => show(active), { passive: true });

        show(0);
        start();
    });
}

/**
 * Image lightbox.
 *
 * One dialog serves the whole page, so a gallery of twelve lab reports costs
 * a single element in the DOM. Triggers are grouped — by `data-lightbox-set`,
 * otherwise by the enclosing `data-lightbox-gallery` — which is what lets the
 * arrows walk through the pictures the visitor actually clicked into. The
 * `href` stays on every trigger, so without JavaScript the image still opens.
 */
function initLightbox() {
    const dialog = document.querySelector('[data-lightbox-dialog]');

    if (!dialog || dialog.dataset.lightboxReady === 'true') {
        return;
    }

    dialog.dataset.lightboxReady = 'true';

    const image = dialog.querySelector('[data-lightbox-image]');
    const caption = dialog.querySelector('[data-lightbox-caption]');
    const counter = dialog.querySelector('[data-lightbox-counter]');
    const original = dialog.querySelector('[data-lightbox-original]');
    const previous = dialog.querySelector('[data-lightbox-prev]');
    const next = dialog.querySelector('[data-lightbox-next]');
    const close = dialog.querySelector('.lightbox__close');

    let triggers = [];
    let position = 0;
    let opener = null;

    const groupFor = (trigger) => {
        const set = trigger.dataset.lightboxSet;

        if (set) {
            return Array.from(document.querySelectorAll('[data-lightbox-set]')).filter(
                (element) => element.dataset.lightboxSet === set,
            );
        }

        const gallery = trigger.closest('[data-lightbox-gallery]');

        return gallery
            ? Array.from(gallery.querySelectorAll('[data-lightbox]'))
            : [trigger];
    };

    const paint = () => {
        const trigger = triggers[position];

        if (!trigger) {
            return;
        }

        const src = trigger.getAttribute('href');
        const text = trigger.dataset.lightboxCaption || '';

        image.src = src;
        image.alt = text;
        caption.textContent = text;
        caption.hidden = text === '';
        counter.textContent = triggers.length > 1 ? `${position + 1} / ${triggers.length}` : '';

        if (original) {
            original.href = src;
        }

        // A lone picture has nowhere to go, so the arrows say so.
        const single = triggers.length < 2;

        previous.disabled = single;
        next.disabled = single;
    };

    const show = (trigger) => {
        triggers = groupFor(trigger);
        position = Math.max(0, triggers.indexOf(trigger));
        opener = trigger;

        dialog.hidden = false;
        dialog.classList.add('is-open');
        document.body.classList.add('has-lightbox');

        paint();
        close.focus();
    };

    const hide = () => {
        dialog.hidden = true;
        dialog.classList.remove('is-open');
        document.body.classList.remove('has-lightbox');

        if (opener) {
            opener.focus();
            opener = null;
        }
    };

    const step = (delta) => {
        position = (position + delta + triggers.length) % triggers.length;
        paint();
    };

    // Delegated from the document because blocks, galleries and product
    // thumbnails arrive from Livewire long after this page was first drawn.
    document.addEventListener('click', (event) => {
        const trigger = event.target.closest?.('[data-lightbox]');

        if (!trigger) {
            return;
        }

        // Command-click and middle-click are the browser's own link gestures;
        // only a plain click is intercepted.
        if (
            event.defaultPrevented ||
            event.metaKey ||
            event.ctrlKey ||
            event.shiftKey ||
            event.altKey ||
            event.button !== 0
        ) {
            return;
        }

        event.preventDefault();
        show(trigger);
    });

    dialog.querySelectorAll('[data-lightbox-close]').forEach((element) => {
        element.addEventListener('click', hide);
    });

    previous.addEventListener('click', () => step(-1));
    next.addEventListener('click', () => step(1));

    document.addEventListener('keydown', (event) => {
        if (dialog.hidden) {
            return;
        }

        if (event.key === 'Escape') {
            hide();

            return;
        }

        if (triggers.length < 2) {
            return;
        }

        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            step(-1);
        }

        if (event.key === 'ArrowRight') {
            event.preventDefault();
            step(1);
        }
    });
}

/**
 * The live chat bubble.
 *
 * The log keeps itself at the newest line through CSS alone, so the one thing
 * left for JavaScript is to put the cursor where the visitor means to type: the
 * composer, as it appears. Watching for the node instead of for the click is
 * deliberate — the panel arrives after a round trip, and a re-render that
 * already had a composer must not steal focus a second time.
 */
let chatWatcher = null;

function initChat() {
    if (chatWatcher !== null) {
        return;
    }

    if (!document.body) {
        return;
    }

    chatWatcher = new MutationObserver((records) => {
        for (const record of records) {
            for (const node of record.addedNodes) {
                if (!(node instanceof HTMLElement)) {
                    continue;
                }

                const field = node.matches('[data-chat-draft]')
                    ? node
                    : node.querySelector('[data-chat-draft]');

                if (field && !field.value) {
                    field.focus({ preventScroll: true });
                }
            }
        }
    });

    chatWatcher.observe(document.body, { childList: true, subtree: true });
}

/** Copy a share link and confirm it on the button itself. */
function initShareButtons() {
    document.querySelectorAll('[data-share]').forEach((button) => {
        button.addEventListener('click', async () => {
            const url = button.dataset.share || window.location.href;
            const original = button.textContent;

            try {
                await navigator.clipboard.writeText(url);
                button.textContent = button.dataset.shareCopied || original;
            } catch {
                window.prompt(original, url);
            }

            window.setTimeout(() => {
                button.textContent = original;
            }, 1800);
        });
    });
}

function boot() {
    document.documentElement.classList.add('js-motion');

    initRevealGroups();
    initReveal();
    initCounters();
    initHeaderMenus();
    initStickyHeader();
    initAccordion();
    initSlider();
    initCarousel();
    initFarmJourney();
    initLightbox();
    initChat();
    initShareButtons();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
} else {
    boot();
}

// Re-run the observers after a Livewire navigation or an island re-render.
document.addEventListener('livewire:navigated', boot);
