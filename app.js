document.addEventListener('DOMContentLoaded', () => {
    document.body.classList.add('is-ready');

    // ── Reveal on scroll ────────────────────────────────
    const revealItems = document.querySelectorAll(
        '.hero-copy, .hero-stage, .feature-panel, .home-panel, .dashboard-panel, ' +
        '.journal-entry, .cta-banner, .collection-shell, .game-card, .vault-logo-card, ' +
        '.detail-hero-shell, .dcard, .form-card, .fsb'
    );
    revealItems.forEach(item => item.classList.add('reveal'));

    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -30px 0px' });

        document.querySelectorAll('.reveal').forEach((item, index) => {
            item.style.transitionDelay = `${Math.min(index * 50, 280)}ms`;
            observer.observe(item);
        });
    } else {
        document.querySelectorAll('.reveal').forEach(item => item.classList.add('is-visible'));
    }

    // ── Hero stage parallax tilt ────────────────────────
    const heroStage = document.querySelector('.hero-stage');
    if (heroStage) {
        const orbit = heroStage.querySelector('.stage-orbit');
        heroStage.addEventListener('pointermove', e => {
            const rect = heroStage.getBoundingClientRect();
            const x = (e.clientX - rect.left) / rect.width;
            const y = (e.clientY - rect.top) / rect.height;
            heroStage.style.transform = `perspective(1200px) rotateX(${(0.5 - y) * 6}deg) rotateY(${(x - 0.5) * 8}deg)`;
            if (orbit) orbit.style.transform = `translate(${(x - 0.5) * 22}px, ${(y - 0.5) * 22}px)`;
        });
        heroStage.addEventListener('pointerleave', () => {
            heroStage.style.transform = 'perspective(1200px) rotateX(0deg) rotateY(0deg)';
            if (orbit) orbit.style.transform = 'translate(0, 0)';
        });
    }

    // ── Card 3D tilt ────────────────────────────────────
    document.querySelectorAll('.game-card, .vault-logo-card').forEach(card => {
        const isCarouselVault = card.classList.contains('vault-logo-card') && card.closest('[data-vault-carousel]');
        if (isCarouselVault) {
            return;
        }

        card.addEventListener('pointermove', e => {
            const rect = card.getBoundingClientRect();
            const x = (e.clientX - rect.left) / rect.width;
            const y = (e.clientY - rect.top) / rect.height;
            card.style.transform = `translateY(-6px) perspective(700px) rotateX(${(0.5 - y) * 5}deg) rotateY(${(x - 0.5) * 5}deg)`;
        });
        card.addEventListener('pointerleave', () => {
            card.style.transform = '';
        });
    });

    // ── Vault carousel ─────────────────────────────────
    document.querySelectorAll('[data-vault-carousel]').forEach(carousel => {
        const viewport = carousel.querySelector('[data-carousel-viewport]');
        const track = carousel.querySelector('.vault-gallery');
        const prevButton = carousel.querySelector('[data-carousel-prev]');
        const nextButton = carousel.querySelector('[data-carousel-next]');
        const cards = Array.from(carousel.querySelectorAll('.vault-logo-card'));

        if (!viewport || !track || cards.length < 2) {
            if (prevButton) prevButton.hidden = true;
            if (nextButton) nextButton.hidden = true;
            return;
        }

        viewport.classList.add('is-animated');
        let activeIndex = 0;
        let pointerStartX = 0;
        let pointerDeltaX = 0;
        let isDragging = false;
        let autoPlayId = null;
        let resumeAutoPlayId = null;

        const syncState = () => {
            cards.forEach((card, index) => {
                const offset = index - activeIndex;
                card.classList.toggle('is-carousel-active', offset === 0);
                card.classList.toggle('is-carousel-prev', offset === -1);
                card.classList.toggle('is-carousel-next', offset === 1);
                card.classList.toggle('is-carousel-far-prev', offset < -1);
                card.classList.toggle('is-carousel-far-next', offset > 1);
            });

            const activeCard = cards[activeIndex];
            const trackWidth = track.scrollWidth;
            const viewportWidth = viewport.clientWidth;
            const cardCenter = activeCard.offsetLeft + activeCard.offsetWidth / 2;
            const targetLeft = cardCenter - viewportWidth / 2;
            const maxShift = Math.max(0, trackWidth - viewportWidth);
            const clampedLeft = Math.max(0, Math.min(targetLeft, maxShift));

            track.style.transform = `translate3d(${-clampedLeft}px, 0, 0)`;

            if (prevButton) prevButton.disabled = activeIndex === 0;
            if (nextButton) nextButton.disabled = activeIndex === cards.length - 1;
        };

        const setActiveIndex = index => {
            if (!cards.length) {
                return;
            }

            activeIndex = ((index % cards.length) + cards.length) % cards.length;
            syncState();
        };

        const moveToCard = direction => {
            setActiveIndex(activeIndex + direction);
        };

        const pauseAutoPlay = (resumeDelay = 4200) => {
            if (autoPlayId) {
                window.clearInterval(autoPlayId);
                autoPlayId = null;
            }

            if (resumeAutoPlayId) {
                window.clearTimeout(resumeAutoPlayId);
                resumeAutoPlayId = null;
            }

            if (resumeDelay >= 0) {
                resumeAutoPlayId = window.setTimeout(() => {
                    startAutoPlay();
                }, resumeDelay);
            }
        };

        const startAutoPlay = () => {
            if (autoPlayId || cards.length < 2) {
                return;
            }

            autoPlayId = window.setInterval(() => {
                setActiveIndex(activeIndex + 1);
            }, 3200);
        };

        const finishSwipe = () => {
            if (!isDragging) {
                return;
            }

            const swipeThreshold = 50;
            const delta = pointerDeltaX;
            isDragging = false;
            pointerDeltaX = 0;
            viewport.classList.remove('is-dragging');

            if (Math.abs(delta) >= swipeThreshold) {
                pauseAutoPlay();
                moveToCard(delta < 0 ? 1 : -1);
            } else {
                syncState();
            }
        };

        if (prevButton) {
            prevButton.addEventListener('click', () => {
                pauseAutoPlay();
                moveToCard(-1);
            });
        }

        if (nextButton) {
            nextButton.addEventListener('click', () => {
                pauseAutoPlay();
                moveToCard(1);
            });
        }

        viewport.addEventListener('keydown', e => {
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                pauseAutoPlay();
                moveToCard(-1);
            }
            if (e.key === 'ArrowRight') {
                e.preventDefault();
                pauseAutoPlay();
                moveToCard(1);
            }
        });

        cards.forEach((card, index) => {
            card.addEventListener('focus', () => {
                pauseAutoPlay();
                setActiveIndex(index);
            });

            card.addEventListener('click', e => {
                if (index !== activeIndex) {
                    e.preventDefault();
                    pauseAutoPlay();
                    setActiveIndex(index);
                }
            });
        });

        viewport.addEventListener('pointerdown', e => {
            if (e.pointerType === 'mouse' && e.button !== 0) {
                return;
            }

            isDragging = true;
            pointerStartX = e.clientX;
            pointerDeltaX = 0;
            viewport.classList.add('is-dragging');
            viewport.setPointerCapture?.(e.pointerId);
            pauseAutoPlay(-1);
        });

        viewport.addEventListener('pointermove', e => {
            if (!isDragging) {
                return;
            }

            pointerDeltaX = e.clientX - pointerStartX;
        });

        viewport.addEventListener('pointerup', finishSwipe);
        viewport.addEventListener('pointercancel', finishSwipe);
        viewport.addEventListener('pointerleave', () => {
            if (isDragging) {
                finishSwipe();
            }
        });

        carousel.addEventListener('mouseenter', () => pauseAutoPlay(-1));
        carousel.addEventListener('mouseleave', () => startAutoPlay());
        carousel.addEventListener('focusin', () => pauseAutoPlay(-1));
        carousel.addEventListener('focusout', () => pauseAutoPlay());

        window.addEventListener('resize', syncState);
        syncState();
        startAutoPlay();
    });

    // ── Progress bar animate-in ─────────────────────────
    const progressFills = document.querySelectorAll('.progress-fill');
    if (progressFills.length) {
        const barObserver = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const el = entry.target;
                    const target = el.style.width;
                    el.style.width = '0%';
                    requestAnimationFrame(() => {
                        requestAnimationFrame(() => { el.style.width = target; });
                    });
                    barObserver.unobserve(el);
                }
            });
        }, { threshold: 0.5 });
        progressFills.forEach(el => barObserver.observe(el));
    }

    // ── Stat counters animate-in ────────────────────────
    const counters = document.querySelectorAll('.si-val[data-count]');
    if (counters.length) {
        const countObserver = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const el = entry.target;
                    const target = parseInt(el.dataset.count, 10);
                    let start = 0;
                    const dur = 900;
                    const startTime = performance.now();
                    const tick = now => {
                        const p = Math.min((now - startTime) / dur, 1);
                        const ease = 1 - Math.pow(1 - p, 3);
                        el.textContent = Math.round(ease * target) + (el.dataset.suffix || '');
                        if (p < 1) requestAnimationFrame(tick);
                    };
                    requestAnimationFrame(tick);
                    countObserver.unobserve(el);
                }
            });
        }, { threshold: 0.5 });
        counters.forEach(el => countObserver.observe(el));
    }

    // ── Delete confirm ──────────────────────────────────
    document.querySelectorAll('form[action="delete.php"]').forEach(form => {
        form.addEventListener('submit', e => {
            if (!confirm('Delete this vault? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });

    // ── Form: rating preview ────────────────────────────
    const ratingSelect = document.querySelector('#Rating');
    if (ratingSelect) {
        const ratingPreview = document.createElement('div');
        ratingPreview.style.cssText = 'color:var(--gold);font-size:1.2rem;margin-top:6px;letter-spacing:0.12em;text-shadow:0 0 10px rgba(255,209,102,0.4);';
        const updateStars = () => {
            const r = parseInt(ratingSelect.value, 10);
            ratingPreview.textContent = '★'.repeat(r) + '☆'.repeat(5 - r);
        };
        ratingSelect.after(ratingPreview);
        ratingSelect.addEventListener('change', updateStars);
        updateStars();
    }
});
