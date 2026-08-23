(function () {
    'use strict';

    var body = document.body;

    function closest(element, selector) {
        if (!element) {
            return null;
        }

        return element.closest(selector);
    }

    function setDrawer(open) {
        body.classList.toggle('drawer-open', open);
        var overlay = document.querySelector('[data-sidebar-overlay]');
        if (overlay) {
            overlay.hidden = !open;
        }
    }

    function getControlledPanel(toggle, fallbackSelector) {
        var panelId = toggle.getAttribute('aria-controls') || toggle.getAttribute('data-filter-target');

        return panelId ? document.getElementById(panelId) : document.querySelector(fallbackSelector);
    }

    function setPanelOpen(toggle, panel, open) {
        panel.hidden = !open;
        panel.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    document.addEventListener('click', function (event) {
        var sidebarToggle = closest(event.target, '[data-sidebar-toggle]');
        if (sidebarToggle) {
            if (window.matchMedia('(max-width: 1023px)').matches) {
                setDrawer(!body.classList.contains('drawer-open'));
            } else {
                body.classList.toggle('sidebar-collapsed');
            }
            return;
        }

        if (closest(event.target, '[data-sidebar-overlay]')) {
            setDrawer(false);
            return;
        }

        var exportTypeTab = closest(event.target, '[data-export-type-tab]');
        if (exportTypeTab) {
            event.preventDefault();
            var exportPanel = closest(exportTypeTab, '[data-export-type-panel]');
            if (exportPanel) {
                var exportType = exportTypeTab.getAttribute('data-export-type') || '';
                var exportLabel = exportTypeTab.getAttribute('data-export-type-label') || exportType;
                var exportForm = closest(exportTypeTab, 'form');
                var exportInput = exportForm ? exportForm.querySelector('[data-export-type-input]') : null;
                var exportLabelTarget = exportPanel.querySelector('[data-export-current-label]');
                var exportOverlay = exportPanel.querySelector('[data-export-panel-overlay]');

                if (exportInput instanceof HTMLInputElement) {
                    exportInput.value = exportType;
                }

                exportPanel.querySelectorAll('[data-export-type-tab]').forEach(function (tab) {
                    var active = tab === exportTypeTab;
                    tab.classList.toggle('is-active', active);
                    tab.setAttribute('aria-selected', active ? 'true' : 'false');
                });

                exportPanel.querySelectorAll('[data-export-current-icon]').forEach(function (icon) {
                    icon.hidden = icon.getAttribute('data-export-current-icon') !== exportType;
                });

                if (exportLabelTarget) {
                    exportLabelTarget.textContent = exportLabel;
                }

                exportPanel.setAttribute('data-export-type-active', exportType);

                if (exportOverlay) {
                    exportOverlay.hidden = false;
                    window.setTimeout(function () {
                        exportOverlay.hidden = true;
                    }, 1000);
                }
            }
            return;
        }

        var searchLoadingTrigger = closest(event.target, '[data-search-loading-trigger]');
        if (searchLoadingTrigger && searchLoadingOverlay) {
            searchLoadingOverlay.hidden = false;
            return;
        }

        var accountSortTrigger = closest(event.target, '[data-account-sort-trigger]');
        if (accountSortTrigger && accountSortOverlay) {
            accountSortOverlay.hidden = false;
            return;
        }

        var publicCancellationTrigger = closest(event.target, '[data-public-cancellation-trigger]');
        if (publicCancellationTrigger) {
            event.preventDefault();
            openPublicCancellationModal(publicCancellationTrigger);
            return;
        }

        if (closest(event.target, '[data-public-cancellation-backdrop]') || closest(event.target, '[data-public-cancellation-cancel]')) {
            closePublicCancellationModal();
            return;
        }

        var filterToggle = closest(event.target, '[data-filter-toggle]');
        if (filterToggle) {
            var filterPanel = getControlledPanel(filterToggle, '[data-filter-panel]');
            if (filterPanel) {
                setPanelOpen(filterToggle, filterPanel, filterPanel.hidden);
            }
            return;
        }

        var moreFilterToggle = closest(event.target, '[data-filter-more-toggle]');
        if (moreFilterToggle) {
            var moreFilterPanel = getControlledPanel(moreFilterToggle, '[data-filter-more-panel]');
            if (moreFilterPanel) {
                var willOpenMoreFilters = moreFilterPanel.hidden;
                setPanelOpen(moreFilterToggle, moreFilterPanel, willOpenMoreFilters);
                var label = moreFilterToggle.querySelector('[data-more-label]');
                if (label) {
                    label.textContent = willOpenMoreFilters ? 'Ocultar filtros adicionales' : 'Mas filtros';
                }
            }
            return;
        }

        var dropdownToggle = closest(event.target, '[data-dropdown-toggle]');
        if (dropdownToggle) {
            var menuId = dropdownToggle.getAttribute('aria-controls');
            var menu = menuId ? document.getElementById(menuId) : null;
            if (menu) {
                var willOpen = menu.hidden;
                document.querySelectorAll('[data-dropdown-menu]').forEach(function (item) {
                    item.hidden = true;
                });
                menu.hidden = !willOpen;
                dropdownToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            }
            return;
        }

        if (!closest(event.target, '[data-dropdown]')) {
            document.querySelectorAll('[data-dropdown-menu]').forEach(function (item) {
                item.hidden = true;
            });
        }

        var toastClose = closest(event.target, '[data-toast-close]');
        if (toastClose) {
            var toast = closest(toastClose, '[data-toast]');
            if (toast) {
                toast.hidden = true;
            }
            return;
        }

        var passwordToggle = closest(event.target, '[data-password-toggle]');
        if (passwordToggle) {
            var target = document.getElementById(passwordToggle.getAttribute('aria-controls') || '');
            if (target) {
                target.type = target.type === 'password' ? 'text' : 'password';
                passwordToggle.setAttribute('aria-pressed', target.type === 'text' ? 'true' : 'false');
            }
        }
    });

    document.addEventListener('input', function (event) {
        var input = closest(event.target, '[data-digits-only]');
        if (input instanceof HTMLInputElement) {
            var maxLength = Number(input.getAttribute('maxlength') || 0);
            var digits = input.value.replace(/\D+/g, '');

            input.value = maxLength > 0 ? digits.slice(0, maxLength) : digits;
            return;
        }

        input = closest(event.target, '[data-alnum-uppercase]');
        if (input instanceof HTMLInputElement) {
            var alnumMaxLength = Number(input.getAttribute('maxlength') || 0);
            var alnum = input.value.replace(/[^a-z0-9]+/gi, '').toUpperCase();

            input.value = alnumMaxLength > 0 ? alnum.slice(0, alnumMaxLength) : alnum;
        }
    });

    document.addEventListener('change', function (event) {
        var perPageSelect = closest(event.target, '[data-workspace-per-page-select]');
        if (perPageSelect instanceof HTMLSelectElement && perPageSelect.form) {
            perPageSelect.form.requestSubmit();
        }
    });

    var pendingConfirmForm = null;
    var pendingPublicCancellationHref = null;
    var modal = document.querySelector('[data-confirm-modal]');
    var modalMessage = document.querySelector('[data-confirm-message]');
    var modalCancel = document.querySelector('[data-confirm-cancel]');
    var modalAccept = document.querySelector('[data-confirm-accept]');
    var modalBackdrop = document.querySelector('[data-confirm-backdrop]');
    var publicCancellationModal = document.querySelector('[data-public-cancellation-modal]');
    var publicCancellationBackdrop = document.querySelector('[data-public-cancellation-backdrop]');
    var publicCancellationCancel = document.querySelector('[data-public-cancellation-cancel]');
    var publicCancellationAccept = document.querySelector('[data-public-cancellation-accept]');
    var searchLoadingOverlay = document.querySelector('[data-search-loading-overlay]');
    var accountSortOverlay = document.querySelector('[data-account-sort-overlay]');
    var accountResetOverlay = document.querySelector('[data-account-reset-overlay]');

    function openPublicCancellationModal(trigger) {
        if (!publicCancellationModal || !publicCancellationBackdrop) {
            window.location.assign(trigger.href);
            return;
        }

        pendingPublicCancellationHref = trigger.href;
        publicCancellationModal.hidden = false;
        publicCancellationBackdrop.hidden = false;

        if (publicCancellationCancel) {
            publicCancellationCancel.focus();
        }
    }

    function closePublicCancellationModal() {
        if (publicCancellationModal) {
            publicCancellationModal.hidden = true;
        }
        if (publicCancellationBackdrop) {
            publicCancellationBackdrop.hidden = true;
        }
        pendingPublicCancellationHref = null;
    }

    function closeModal() {
        if (modal) {
            modal.hidden = true;
        }
        if (modalBackdrop) {
            modalBackdrop.hidden = true;
        }
        pendingConfirmForm = null;
    }

    function setLoading(form) {
        var submitter = form.querySelector('[type="submit"]');
        if (!submitter || submitter.disabled) {
            return;
        }

        submitter.dataset.originalHtml = submitter.innerHTML;
        submitter.dataset.originalText = submitter.textContent;
        submitter.disabled = true;
        submitter.setAttribute('aria-busy', 'true');
        submitter.innerHTML = '<span class="spinner" aria-hidden="true"></span><span>Procesando</span>';
    }

    function resetLoading(form) {
        var submitter = form.querySelector('[type="submit"][aria-busy="true"]');
        if (!submitter) {
            return;
        }

        submitter.disabled = false;
        submitter.removeAttribute('aria-busy');

        if (submitter.dataset.originalHtml) {
            submitter.innerHTML = submitter.dataset.originalHtml;
        } else if (submitter.dataset.originalText) {
            submitter.textContent = submitter.dataset.originalText;
        }

        delete submitter.dataset.originalHtml;
        delete submitter.dataset.originalText;
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        var message = form.getAttribute('data-confirm');
        if (message && modal && !form.dataset.confirmed) {
            event.preventDefault();
            pendingConfirmForm = form;
            if (modalMessage) {
                modalMessage.textContent = message;
            }
            modal.hidden = false;
            if (modalBackdrop) {
                modalBackdrop.hidden = false;
            }
            if (modalCancel) {
                modalCancel.focus();
            }
            return;
        }

        if (form.matches('[data-loading]')) {
            setLoading(form);

            if (form.matches('[data-download]')) {
                window.setTimeout(function () {
                    resetLoading(form);
                }, Number(form.getAttribute('data-loading-reset-ms') || 3500));
            }
        }

        if (form.matches('[data-search-loading]') && searchLoadingOverlay) {
            searchLoadingOverlay.hidden = false;
        }

        if (form.matches('[data-account-reset-loading]') && accountResetOverlay) {
            accountResetOverlay.hidden = false;
        }
    });

    if (modalCancel) {
        modalCancel.addEventListener('click', closeModal);
    }

    if (modalBackdrop) {
        modalBackdrop.addEventListener('click', closeModal);
    }

    if (modalAccept) {
        modalAccept.addEventListener('click', function () {
            if (!pendingConfirmForm) {
                closeModal();
                return;
            }
            pendingConfirmForm.dataset.confirmed = 'true';
            if (pendingConfirmForm.matches('[data-account-reset-loading]') && accountResetOverlay) {
                accountResetOverlay.hidden = false;
            }
            setLoading(pendingConfirmForm);
            pendingConfirmForm.submit();
        });
    }

    if (publicCancellationAccept) {
        publicCancellationAccept.addEventListener('click', function () {
            if (!pendingPublicCancellationHref) {
                closePublicCancellationModal();
                return;
            }

            window.location.assign(pendingPublicCancellationHref);
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeModal();
            closePublicCancellationModal();
            setDrawer(false);
        }
    });

    window.setTimeout(function () {
        document.querySelectorAll('[data-toast]').forEach(function (toast) {
            toast.hidden = true;
        });
    }, 6000);
})();
