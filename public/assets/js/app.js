(() => {
    const normalize = (value) =>
        (value || '')
            .toString()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');

    const updateVisibleCount = (container, cards) => {
        const resultCount = container.querySelector('[data-js="result-count"]');
        if (!resultCount) {
            return;
        }

        const visible = cards.filter((card) => !card.classList.contains('hidden')).length;
        const total = cards.length;
        resultCount.textContent = `${visible} résultat${visible > 1 ? 's' : ''} sur ${total}`;
    };

    const toggleEmptyState = (container, cards) => {
        const emptyState = container.querySelector('[data-js="empty-state"]');
        if (!emptyState) {
            return;
        }

        const hasVisibleCard = cards.some((card) => !card.classList.contains('hidden'));
        emptyState.classList.toggle('hidden', hasVisibleCard);
    };

    const setupRevealAnimation = () => {
        const revealCards = document.querySelectorAll('.reveal');
        if (revealCards.length === 0) {
            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('reveal-visible');
                        observer.unobserve(entry.target);
                    }
                });
            },
            {
                threshold: 0.15,
            }
        );

        revealCards.forEach((card) => observer.observe(card));
    };

    const setupBackToTop = () => {
        const button = document.querySelector('[data-js="back-top"]');
        if (!button) {
            return;
        }

        const syncVisibility = () => {
            button.classList.toggle('hidden', window.scrollY < 320);
        };

        button.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        window.addEventListener('scroll', syncVisibility, { passive: true });
        syncVisibility();
    };

    const setupSearchGrid = (gridSelector, toolbarSelector) => {
        const grid = document.querySelector(gridSelector);
        const toolbar = document.querySelector(toolbarSelector);
        if (!grid || !toolbar) {
            return;
        }

        const cards = [...grid.querySelectorAll('.card')];
        const searchInput = toolbar.querySelector('[data-js="search-input"]');

        const applyFilters = () => {
            const query = normalize(searchInput ? searchInput.value : '');

            cards.forEach((card) => {
                const searchTerms = card.dataset.search || card.dataset.name || '';
                const matchText = normalize(searchTerms).includes(query);
                card.classList.toggle('hidden', !matchText);
            });

            updateVisibleCount(document, cards);
            toggleEmptyState(document, cards);
        };

        if (searchInput) {
            searchInput.addEventListener('input', applyFilters);
        }

        applyFilters();
    };

    const setupRoulette = () => {
        const openButton = document.querySelector('[data-js="open-roulette"]');
        const closeButton = document.querySelector('[data-js="close-roulette"]');
        const modalBackdrop = document.querySelector('[data-js="roulette-modal-backdrop"]');
        const roulette = document.querySelector('[data-js="roulette"]');
        if (!roulette || !openButton || !modalBackdrop) {
            return;
        }

        const select = roulette.querySelector('[data-js="roulette-select"]');
        const launchButton = roulette.querySelector('[data-js="roulette-launch"]');
        const rollBox = roulette.querySelector('[data-js="roulette-roll"]');
        const emptyBox = roulette.querySelector('[data-js="roulette-empty"]');
        const resultBox = roulette.querySelector('[data-js="roulette-result"]');
        const resultPhoto = roulette.querySelector('[data-js="roulette-photo"]');
        const resultName = roulette.querySelector('[data-js="roulette-name"]');
        const resultDescription = roulette.querySelector('[data-js="roulette-description"]');
        const resultNote = roulette.querySelector('[data-js="roulette-note"]');
        const resultStatus = roulette.querySelector('[data-js="roulette-status"]');
        const resultLink = roulette.querySelector('[data-js="roulette-link"]');

        if (!select || !launchButton || !rollBox || !emptyBox || !resultBox) {
            return;
        }

        let toilettesByBatiment = {};
        try {
            toilettesByBatiment = JSON.parse(roulette.dataset.toilettesByBatiment || '{}');
        } catch (error) {
            toilettesByBatiment = {};
        }

        const hidePanels = () => {
            emptyBox.classList.add('hidden');
            rollBox.classList.add('hidden');
            resultBox.classList.add('hidden');
        };

        const openModal = () => {
            modalBackdrop.classList.remove('hidden');
            document.body.classList.add('modal-open');
            hidePanels();
        };

        const closeModal = () => {
            modalBackdrop.classList.add('hidden');
            document.body.classList.remove('modal-open');
            rollBox.classList.remove('rolling');
        };

        openButton.addEventListener('click', openModal);

        if (closeButton) {
            closeButton.addEventListener('click', closeModal);
        }

        modalBackdrop.addEventListener('click', (event) => {
            if (event.target === modalBackdrop) {
                closeModal();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !modalBackdrop.classList.contains('hidden')) {
                closeModal();
            }
        });

        const showResult = (toilette) => {
            const avisCount = Number(toilette.avis_count || 0);
            const noteMoyenne = Number(toilette.note_moyenne || 0);
            const statusRaw = (toilette.statut || '').toLowerCase();
            const isOpen = statusRaw === 'ouvert';

            if (resultPhoto) {
                resultPhoto.src = toilette.photo || '';
                resultPhoto.alt = `Toilette ${toilette.nom || ''}`;
            }
            if (resultName) {
                resultName.textContent = toilette.nom || 'Toilette sélectionnée';
            }
            if (resultDescription) {
                resultDescription.textContent = toilette.description || '';
            }
            if (resultNote) {
                resultNote.textContent =
                    avisCount === 0
                        ? "Note : il n'y a pas encore assez d'avis"
                        : `Note moyenne : ${noteMoyenne}/5 (${avisCount} avis)`;
            }
            if (resultStatus) {
                resultStatus.classList.remove('open', 'closed');
                resultStatus.classList.add(isOpen ? 'open' : 'closed');
                resultStatus.textContent = isOpen ? 'Ouvert' : 'Fermé';
            }
            if (resultLink) {
                resultLink.href = `?page=batiment&id=${Number(toilette.batiment_id || 0)}`;
            }

            resultBox.classList.remove('hidden');
        };

        launchButton.addEventListener('click', () => {
            const batimentId = select.value;
            if (!batimentId) {
                hidePanels();
                rollBox.classList.remove('hidden');
                rollBox.classList.remove('rolling');
                rollBox.textContent = 'Choisis un bâtiment avant de lancer la roulette.';
                return;
            }

            const list = Array.isArray(toilettesByBatiment[batimentId]) ? toilettesByBatiment[batimentId] : [];
            hidePanels();

            if (list.length === 0) {
                rollBox.classList.add('hidden');
                emptyBox.classList.remove('hidden');
                return;
            }

            let tick = 0;
            rollBox.classList.remove('hidden');
            rollBox.classList.add('rolling');

            const rolling = window.setInterval(() => {
                const current = list[tick % list.length];
                rollBox.textContent = `... ${current.nom || 'Toilette'} ...`;
                tick += 1;
            }, 90);

            window.setTimeout(() => {
                window.clearInterval(rolling);
                rollBox.classList.remove('rolling');

                const selected = list[Math.floor(Math.random() * list.length)];
                rollBox.textContent = `Resultat : ${selected.nom || 'Toilette'}`;
                showResult(selected);
            }, 1700);
        });
    };

    document.addEventListener('DOMContentLoaded', () => {
        setupRevealAnimation();
        setupBackToTop();
        setupSearchGrid('[data-js="batiment-grid"]', '[data-js="batiment-toolbar"]');
        setupSearchGrid('[data-js="toilette-grid"]', '[data-js="toilette-toolbar"]');
        setupRoulette();
    });
})();
