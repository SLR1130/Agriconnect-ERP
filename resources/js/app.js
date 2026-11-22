import { farmOutlineSquareMeters, farmOutlineInteriorPoint } from './farm-area.js';

let googleMapsPromise;
function loadGoogleMaps(key) {
    if (window.google?.maps?.Map) return Promise.resolve(window.google.maps);
    if (googleMapsPromise) return googleMapsPromise;
    googleMapsPromise = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        const timer = window.setTimeout(() => reject(new Error('Google Maps timed out. Check the connection and try again.')), 15000);
        window.agriconnectGoogleMapsReady = () => {
            window.clearTimeout(timer);
            resolve(window.google.maps);
        };
        window.gm_authFailure = () => {
            window.clearTimeout(timer);
            reject(new Error('Google Maps rejected the key. Check its API and website restrictions.'));
        };
        script.onerror = () => {
            window.clearTimeout(timer);
            reject(new Error('Google Maps could not load. Check your connection.'));
        };
        const params = new URLSearchParams({ key, loading: 'async', callback: 'agriconnectGoogleMapsReady' });
        script.src = `https://maps.googleapis.com/maps/api/js?${params}`;
        script.async = true;
        document.head.append(script);
    });
    return googleMapsPromise;
}

document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('app-sidebar');
    const shell = document.querySelector('.erp-shell');
    const sidebarToggle = document.getElementById('mobile-sidebar-toggle');
    const sidebarCloseButton = document.getElementById('mobile-sidebar-close');
    const desktopSidebarToggle = document.getElementById('desktop-sidebar-toggle');
    const backdrop = document.getElementById('mobile-sidebar-backdrop');
    let drawerOpener = null;
    const mobileDrawer = () => window.matchMedia('(max-width: 1023px)').matches;

    const closeSidebar = (restoreFocus = false) => {
        sidebar?.classList.remove('is-open');
        sidebarToggle?.setAttribute('aria-expanded', 'false');
        if (backdrop) backdrop.hidden = true;
        document.body.classList.remove('erp-nav-open');
        sidebar?.removeAttribute('aria-modal');
        sidebar?.removeAttribute('role');
        if (sidebar) sidebar.inert = mobileDrawer();
        if (restoreFocus) {
            drawerOpener?.focus();
            drawerOpener = null;
        }
    };

    sidebarToggle?.addEventListener('click', () => {
        if (sidebar?.classList.contains('is-open')) {
            closeSidebar(true);
            return;
        }
        drawerOpener = sidebarToggle;
        sidebar?.classList.add('is-open');
        if (sidebar) {
            sidebar.inert = false;
            sidebar.setAttribute('role', 'dialog');
            sidebar.setAttribute('aria-modal', 'true');
        }
        document.body.classList.add('erp-nav-open');
        sidebarToggle.setAttribute('aria-expanded', 'true');
        if (backdrop) backdrop.hidden = false;
        requestAnimationFrame(() => sidebar?.querySelector('button:not([disabled]), a[href]')?.focus());
    });
    sidebarCloseButton?.addEventListener('click', () => closeSidebar(true));
    backdrop?.addEventListener('click', () => closeSidebar(true));
    sidebar?.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => closeSidebar(false)));
    if (sidebar && mobileDrawer()) {
        sidebar.inert = true;
    }
    window.addEventListener('resize', () => {
        if (!mobileDrawer()) closeSidebar(false);
        else if (!sidebar?.classList.contains('is-open') && sidebar) sidebar.inert = true;
    });

    const setDesktopSidebarCollapsed = (collapsed) => {
        if (!shell || !desktopSidebarToggle) return;
        shell.classList.toggle('is-sidebar-collapsed', collapsed);
        desktopSidebarToggle.setAttribute('aria-expanded', String(!collapsed));
        desktopSidebarToggle.setAttribute('aria-label', collapsed ? 'Expand navigation' : 'Collapse navigation');
        desktopSidebarToggle.setAttribute('title', collapsed ? 'Expand navigation' : 'Collapse navigation');
    };

    try {
        setDesktopSidebarCollapsed(localStorage.getItem('agriconnect-sidebar-collapsed') === 'true');
    } catch {
        setDesktopSidebarCollapsed(false);
    }

    desktopSidebarToggle?.addEventListener('click', () => {
        const collapsed = !shell?.classList.contains('is-sidebar-collapsed');
        setDesktopSidebarCollapsed(collapsed);
        try {
            localStorage.setItem('agriconnect-sidebar-collapsed', String(collapsed));
        } catch {
            // The sidebar remains usable when browser storage is unavailable.
        }
    });

    const menus = [
        ['profile-menu-toggle', 'profile-menu'],
        ['notification-menu-toggle', 'notification-menu'],
    ];

    menus.forEach(([toggleId, menuId]) => {
        const toggle = document.getElementById(toggleId);
        const menu = document.getElementById(menuId);
        if (!toggle || !menu) return;

        toggle.addEventListener('click', (event) => {
            event.stopPropagation();
            const open = menu.hidden;
            document.querySelectorAll('[data-topbar-menu]').forEach((item) => { item.hidden = true; });
            document.querySelectorAll('[data-menu-toggle]').forEach((item) => { item.setAttribute('aria-expanded', 'false'); });
            menu.hidden = !open;
            toggle.setAttribute('aria-expanded', String(open));
        });
    });

    document.addEventListener('click', () => {
        document.querySelectorAll('[data-topbar-menu]').forEach((menu) => { menu.hidden = true; });
        document.querySelectorAll('[data-menu-toggle]').forEach((toggle) => { toggle.setAttribute('aria-expanded', 'false'); });
    });

    document.addEventListener('keydown', (event) => {
        if (mobileDrawer() && sidebar?.classList.contains('is-open')) {
            const focusable = [...sidebar.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')];
            if (event.key === 'Tab' && focusable.length) {
                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            }
            if (event.key === 'Escape') {
                closeSidebar(true);
                return;
            }
        }
        if (event.key !== 'Escape') return;

        document.querySelectorAll('[data-topbar-menu]').forEach((menu) => { menu.hidden = true; });
        document.querySelectorAll('[data-menu-toggle]').forEach((toggle) => { toggle.setAttribute('aria-expanded', 'false'); });
    });

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('form[data-confirm-submit]');
        if (form && !window.confirm(form.dataset.confirmSubmit)) event.preventDefault();
    });

    document.querySelectorAll('form[data-private-document-upload]').forEach((form) => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('button[type="submit"]');
            const progress = form.querySelector('[data-document-upload-progress]');
            if (progress) progress.hidden = false;
            if (button) {
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
                button.textContent = 'Uploading securely…';
            }
        });
    });

    document.querySelectorAll('[data-publication-gallery-input]').forEach((input) => {
        const form = input.closest('form');
        const list = form?.querySelector('[data-publication-gallery-alt-list]');
        if (!list) return;

        input.addEventListener('change', () => {
            list.replaceChildren();
            const files = [...(input.files || [])];
            if (files.length > 6) {
                input.value = '';
                list.textContent = 'Select no more than six gallery photographs.';
                return;
            }

            files.forEach((file, index) => {
                const wrapper = document.createElement('div');
                wrapper.className = 'farmer-field publication-gallery-alt';
                const label = document.createElement('label');
                const field = document.createElement('input');
                field.type = 'text';
                field.name = `gallery_alt[${index}]`;
                field.maxLength = 180;
                field.required = true;
                field.placeholder = 'Describe the photograph';
                label.htmlFor = `${input.id || 'publication-gallery'}-alt-${index}`;
                label.textContent = `Alternative text for ${file.name}`;
                field.id = label.htmlFor;
                wrapper.append(label, field);
                list.append(wrapper);
            });
        });
    });

    document.querySelectorAll('[data-publication-media-list]').forEach((list) => {
        list.querySelectorAll('[data-publication-media-item]').forEach((row) => {
            const keep = row.querySelector('input[name="keep_media_ids[]"]');
            const cover = row.querySelector('input[name="existing_cover_id"]');
            if (!keep || !cover) return;
            cover.disabled = !keep.checked;
            keep.addEventListener('change', () => {
                cover.disabled = !keep.checked;
                if (!keep.checked && cover.checked) cover.checked = false;
            });
        });
        list.addEventListener('click', (event) => {
            const button = event.target.closest('[data-publication-move]');
            const item = button?.closest('[data-publication-media-item]');
            if (!button || !item) return;
            if (button.dataset.publicationMove === 'up' && item.previousElementSibling) {
                list.insertBefore(item, item.previousElementSibling);
            } else if (button.dataset.publicationMove === 'down' && item.nextElementSibling) {
                list.insertBefore(item.nextElementSibling, item);
            }
            list.querySelectorAll('[data-publication-media-item]').forEach((row, index, rows) => {
                const up = row.querySelector('[data-publication-move="up"]');
                const down = row.querySelector('[data-publication-move="down"]');
                if (up) up.disabled = index === 0;
                if (down) down.disabled = index === rows.length - 1;
            });
            button.focus();
        });
        list.querySelectorAll('[data-publication-media-item]').forEach((row, index, rows) => {
            row.querySelector('[data-publication-move="up"]')?.toggleAttribute('disabled', index === 0);
            row.querySelector('[data-publication-move="down"]')?.toggleAttribute('disabled', index === rows.length - 1);
        });
    });

    document.addEventListener('click', (event) => {
        const cancel = event.target.closest('[data-ffrms-close-edit]');
        if (!cancel) return;
        const disclosure = cancel.closest('details');
        disclosure?.querySelector('form')?.reset();
        if (disclosure) {
            disclosure.open = false;
            disclosure.querySelector('summary')?.focus();
        }
    });

    document.querySelectorAll('[data-erp-workspace]').forEach((workspace) => {
        const tabs = [...workspace.querySelectorAll('[data-erp-workspace-tab]')];
        const parameter = workspace.dataset.workspaceParam || 'workspace';
        if (!tabs.length) return;

        const updateWorkspace = (key, updateUrl = false) => {
            const selected = tabs.some((tab) => tab.dataset.erpWorkspaceTab === key)
                ? key
                : (tabs[0].dataset.erpWorkspaceTab || 'overview');

            tabs.forEach((tab) => {
                const active = tab.dataset.erpWorkspaceTab === selected;
                tab.setAttribute('aria-selected', String(active));
                tab.tabIndex = active ? 0 : -1;
                const panel = document.getElementById(tab.getAttribute('aria-controls'));
                if (panel) panel.hidden = !active;
            });

            if (updateUrl) {
                const url = new URL(window.location.href);
                url.searchParams.set(parameter, selected);
                window.history.pushState({}, '', `${url.pathname}${url.search}${url.hash}`);
            }
        };

        tabs.forEach((tab) => {
            tab.addEventListener('click', () => {
                updateWorkspace(tab.dataset.erpWorkspaceTab, true);
                tab.focus();
            });
            tab.addEventListener('keydown', (event) => {
                let index = tabs.indexOf(tab);
                if (event.key === 'ArrowRight' || event.key === 'ArrowDown') index = (index + 1) % tabs.length;
                else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') index = (index - 1 + tabs.length) % tabs.length;
                else if (event.key === 'Home') index = 0;
                else if (event.key === 'End') index = tabs.length - 1;
                else return;
                event.preventDefault();
                tabs[index].focus();
                tabs[index].click();
            });
        });

        workspace.querySelectorAll('[data-erp-workspace-select]').forEach((trigger) => {
            trigger.addEventListener('click', () => {
                const key = trigger.dataset.erpWorkspaceSelect;
                updateWorkspace(key, true);
                tabs.find((tab) => tab.dataset.erpWorkspaceTab === key)?.focus();
            });
        });

        workspace.querySelectorAll('form').forEach((form) => {
            const panel = form.closest('[data-erp-workspace-panel]');
            if (!panel) return;

            form.addEventListener('submit', () => {
                const selected = tabs.find((tab) => tab.getAttribute('aria-selected') === 'true')?.dataset.erpWorkspaceTab
                    || panel.dataset.erpWorkspacePanel;

                if (form.method.toUpperCase() === 'GET') {
                    let field = form.querySelector(`[name="${parameter}"]`);
                    if (!field) {
                        field = document.createElement('input');
                        field.type = 'hidden';
                        field.name = parameter;
                        form.append(field);
                    }
                    field.value = selected;
                    return;
                }

                let field = form.querySelector('[name="_erp_workspace"]');
                if (!field) {
                    field = document.createElement('input');
                    field.type = 'hidden';
                    field.name = '_erp_workspace';
                    form.append(field);
                }
                field.value = selected;
            });
        });

        const applyUrlWorkspace = () => {
            const params = new URLSearchParams(window.location.search);
            updateWorkspace(params.get(parameter) || workspace.dataset.workspaceInitial || workspace.dataset.workspaceDefault);
        };
        window.addEventListener('popstate', applyUrlWorkspace);
        applyUrlWorkspace();
    });

    const workspace = document.querySelector('[data-apms-workspace]');

    if (workspace) {
        const primaryTabs = [...workspace.querySelectorAll('[role="tablist"] [data-apms-primary-tab]')];
        const recordTabs = [...workspace.querySelectorAll('[data-apms-record-tab]')];
        const monitoringTabs = [...workspace.querySelectorAll('[data-apms-monitoring-tab]')];

        const setQuery = (updates, push = true) => {
            const url = new URL(window.location.href);
            Object.entries(updates).forEach(([key, value]) => url.searchParams.set(key, value));
            window.history[push ? 'pushState' : 'replaceState']({}, '', `${url.pathname}${url.search}${url.hash}`);
        };

        const setSelectedTab = (key) => {
            primaryTabs.forEach((tab) => {
                const selected = tab.dataset.apmsPrimaryTab === key;
                tab.setAttribute('aria-selected', String(selected));
                tab.tabIndex = selected ? 0 : -1;
                tab.classList.toggle('is-active', selected);
                const panel = document.getElementById(tab.getAttribute('aria-controls'));
                if (panel) panel.hidden = !selected;
            });
        };

        const setSelectedRecordType = (key) => {
            recordTabs.forEach((tab) => {
                const selected = tab.dataset.apmsRecordTab === key;
                tab.setAttribute('aria-selected', String(selected));
                tab.tabIndex = selected ? 0 : -1;
                tab.classList.toggle('is-active', selected);
                const panel = document.getElementById(tab.getAttribute('aria-controls'));
                if (panel) panel.hidden = !selected;
            });
        };

        const setSelectedMonitoringType = (key) => {
            monitoringTabs.forEach((tab) => {
                const selected = tab.dataset.apmsMonitoringTab === key;
                tab.setAttribute('aria-selected', String(selected));
                tab.tabIndex = selected ? 0 : -1;
                tab.classList.toggle('is-active', selected);
                const panel = document.getElementById(tab.getAttribute('aria-controls'));
                if (panel) panel.hidden = !selected;
            });
        };

        const applyUrlState = () => {
            const params = new URLSearchParams(window.location.search);
            const tab = params.get('tab') || workspace.dataset.initialTab || 'overview';
            const recordType = params.get('record_type') || workspace.dataset.initialRecordType || 'crops';
            const monitoringType = params.get('monitoring_type') || workspace.dataset.initialMonitoringType || 'incidents';
            setSelectedTab(primaryTabs.some((item) => item.dataset.apmsPrimaryTab === tab) ? tab : 'overview');
            setSelectedRecordType(recordTabs.some((item) => item.dataset.apmsRecordTab === recordType) ? recordType : 'crops');
            setSelectedMonitoringType(monitoringTabs.some((item) => item.dataset.apmsMonitoringTab === monitoringType) ? monitoringType : 'incidents');
        };

        const handleTabClick = (event, tab, key, parameter, parentTab = null) => {
            if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            if (parentTab) setSelectedTab(parentTab);
            setSelectedTab(parentTab || (parameter === 'tab' ? key : new URLSearchParams(window.location.search).get('tab') || 'overview'));
            if (parameter === 'tab') setQuery({ tab: key });
            if (parameter === 'record_type') setQuery({ tab: 'production', record_type: key });
            if (parameter === 'monitoring_type') setQuery({ tab: 'monitoring', monitoring_type: key });
            if (parameter === 'tab') return;
            if (parameter === 'record_type') setSelectedRecordType(key);
            if (parameter === 'monitoring_type') setSelectedMonitoringType(key);
            tab.focus();
        };

        primaryTabs.forEach((tab) => tab.addEventListener('click', (event) => handleTabClick(event, tab, tab.dataset.apmsPrimaryTab, 'tab')));
        recordTabs.forEach((tab) => tab.addEventListener('click', (event) => handleTabClick(event, tab, tab.dataset.apmsRecordTab, 'record_type', 'production')));
        monitoringTabs.forEach((tab) => tab.addEventListener('click', (event) => handleTabClick(event, tab, tab.dataset.apmsMonitoringTab, 'monitoring_type', 'monitoring')));

        workspace.querySelectorAll('a[data-apms-primary-tab]:not([role="tab"])').forEach((link) => {
            link.addEventListener('click', (event) => {
                if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
                const key = link.dataset.apmsPrimaryTab;
                if (!primaryTabs.some((tab) => tab.dataset.apmsPrimaryTab === key)) return;
                event.preventDefault();
                setSelectedTab(key);
                setQuery({ tab: key });
            });
        });

        workspace.querySelectorAll('[role="tablist"] [role="tab"]').forEach((tab) => {
            tab.addEventListener('keydown', (event) => {
                const tabs = [...tab.closest('[role="tablist"]').querySelectorAll('[role="tab"]')];
                let index = tabs.indexOf(tab);
                if (event.key === 'ArrowRight' || event.key === 'ArrowDown') index = (index + 1) % tabs.length;
                else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') index = (index - 1 + tabs.length) % tabs.length;
                else if (event.key === 'Home') index = 0;
                else if (event.key === 'End') index = tabs.length - 1;
                else return;
                event.preventDefault();
                tabs[index].focus();
                tabs[index].click();
            });
        });

        window.addEventListener('popstate', applyUrlState);
        applyUrlState();

        const editorDialog = document.getElementById('apms-editor-dialog');
        const editorContent = document.getElementById('apms-editor-content');
        const editorTitle = document.getElementById('apms-editor-title');
        let editorTrigger = null;

        const filterFarmOptions = (form, selectedFarmerId, selectedFarmId = null) => {
            const farmSelect = form.querySelector('[data-apms-farm-select]');
            if (!farmSelect) return;

            [...farmSelect.options].forEach((option) => {
                if (!option.value) return;
                const matches = !selectedFarmerId || option.dataset.farmerId === String(selectedFarmerId);
                option.hidden = !matches;
                option.disabled = !matches;
            });

            if (selectedFarmId) farmSelect.value = String(selectedFarmId);
            if (farmSelect.selectedOptions[0]?.disabled) farmSelect.value = '';
        };

        const openEditor = (trigger, preserveOldInput = false) => {
            if (!editorDialog || !editorContent) return;
            editorTrigger = trigger;
            const type = trigger.dataset.apmsOpenEditor;
            const template = document.getElementById(`apms-form-${type}`);
            if (!template) return;

            editorContent.replaceChildren(template.content.cloneNode(true));
            const form = editorContent.querySelector('[data-apms-editor-form]');
            if (!form) return;

            const editing = trigger.dataset.apmsMode === 'edit';
            const method = form.querySelector('[name="_method"]');
            const formType = form.querySelector('[name="_apms_form"]');
            const recordId = form.querySelector('[name="_apms_record_id"]');
            form.action = editing ? trigger.dataset.apmsAction : form.dataset.storeAction;
            method.value = editing ? 'PATCH' : '';
            formType.value = type;
            recordId.value = editing ? trigger.dataset.apmsRecordId : '';
            editorTitle.textContent = trigger.dataset.apmsTitle || `${editing ? 'Edit' : 'Add'} APMS record`;
            editorDialog.dataset.dirty = 'false';

            if (editing && !preserveOldInput) {
                const values = JSON.parse(trigger.dataset.apmsRecord || '{}');
                Object.entries(values).forEach(([name, value]) => {
                    const field = form.elements.namedItem(name);
                    if (field && value !== null && value !== undefined) field.value = value;
                });
            }

            const monitoringTypeChoice = form.querySelector('[data-apms-monitoring-type-choice]');
            const monitoringTypeValue = form.querySelector('[data-apms-monitoring-type-value]');
            const monitoringTypeCustom = form.querySelector('[data-apms-monitoring-custom]');
            const monitoringTypeCustomInput = form.querySelector('[data-apms-monitoring-type-custom]');
            if (monitoringTypeChoice && monitoringTypeValue && monitoringTypeCustom && monitoringTypeCustomInput) {
                const isKnownType = [...monitoringTypeChoice.options].some((option) => option.value === monitoringTypeValue.value);
                monitoringTypeChoice.value = isKnownType ? monitoringTypeValue.value : '__custom__';
                monitoringTypeCustom.hidden = isKnownType;
                monitoringTypeCustomInput.required = !isKnownType;
                monitoringTypeCustomInput.value = isKnownType ? '' : monitoringTypeValue.value;

                monitoringTypeChoice.addEventListener('change', () => {
                    const custom = monitoringTypeChoice.value === '__custom__';
                    monitoringTypeCustom.hidden = !custom;
                    monitoringTypeCustomInput.required = custom;
                    monitoringTypeValue.value = custom ? monitoringTypeCustomInput.value : monitoringTypeChoice.value;
                    if (custom) monitoringTypeCustomInput.focus();
                });
                monitoringTypeCustomInput.addEventListener('input', () => {
                    monitoringTypeValue.value = monitoringTypeCustomInput.value;
                });
            }

            const farmerSelect = form.querySelector('[data-apms-farmer-select]');
            const farmSelect = form.querySelector('[data-apms-farm-select]');
            filterFarmOptions(form, farmerSelect?.value, farmSelect?.value);
            farmerSelect?.addEventListener('change', () => filterFarmOptions(form, farmerSelect.value));
            farmSelect?.addEventListener('change', () => {
                const option = farmSelect.selectedOptions[0];
                if (option?.dataset.farmerId && farmerSelect) {
                    farmerSelect.value = option.dataset.farmerId;
                    filterFarmOptions(form, farmerSelect.value, farmSelect.value);
                }
            });

            form.addEventListener('input', () => { editorDialog.dataset.dirty = 'true'; });
            form.addEventListener('change', () => { editorDialog.dataset.dirty = 'true'; });
            form.addEventListener('submit', (event) => {
                const submit = event.submitter || form.querySelector('[data-apms-submit]');
                if (submit) {
                    submit.disabled = true;
                    submit.setAttribute('aria-busy', 'true');
                    submit.textContent = 'Saving…';
                }
            });

            if (!editorDialog.open) editorDialog.showModal();
            requestAnimationFrame(() => form.querySelector('input:not([type="hidden"]), select, textarea')?.focus());
        };

        editorDialog?.addEventListener('close', () => {
            if (editorTrigger?.isConnected) editorTrigger.focus();
            editorTrigger = null;
        });

        workspace.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-apms-open-editor]');
            if (!trigger) return;
            const errorForm = workspace.dataset.errorForm;
            const errorRecord = workspace.dataset.errorRecord;
            const preserve = errorForm === trigger.dataset.apmsOpenEditor
                && (errorRecord ? errorRecord === trigger.dataset.apmsRecordId : trigger.dataset.apmsMode !== 'edit');
            openEditor(trigger, preserve);
        });

        editorDialog?.addEventListener('click', (event) => {
            const closeButton = event.target.closest('[data-apms-close]');
            if (closeButton) {
                if (editorDialog.dataset.dirty === 'true' && !window.confirm('Discard the unsaved changes in this form?')) return;
                editorDialog.close();
                return;
            }

            if (event.target !== editorDialog) return;
            if (editorDialog.dataset.dirty === 'true' && !window.confirm('Discard the unsaved changes in this form?')) return;
            editorDialog.close();
        });

        editorDialog?.addEventListener('cancel', (event) => {
            if (editorDialog.dataset.dirty === 'true' && !window.confirm('Discard the unsaved changes in this form?')) event.preventDefault();
        });

        const errorForm = workspace.dataset.errorForm;
        const errorRecord = workspace.dataset.errorRecord;
        if (errorForm) {
            const trigger = [...workspace.querySelectorAll('[data-apms-open-editor]')].find((item) => {
                if (item.dataset.apmsOpenEditor !== errorForm) return false;
                return errorRecord
                    ? item.dataset.apmsMode === 'edit' && item.dataset.apmsRecordId === errorRecord
                    : item.dataset.apmsMode !== 'edit';
            });
            if (trigger) openEditor(trigger, true);
        }
    }

    document.querySelectorAll('[data-apms-review-form]').forEach((form) => {
        const decision = form.querySelector('[data-apms-review-decision]');
        const notes = form.querySelector('[data-apms-review-notes]');
        const help = form.querySelector('#apms-review-notes-help');
        if (!decision || !notes || !help) return;

        const syncReviewNotes = () => {
            const required = ['returned', 'rejected'].includes(decision.value);
            notes.required = required;
            notes.setAttribute('aria-required', String(required));
            help.textContent = required
                ? 'Required for this decision. Explain what the farmer should correct or why the submission cannot be accepted.'
                : 'Optional for approval. Clear notes help the farmer understand the recorded outcome.';
        };

        decision.addEventListener('change', syncReviewNotes);
        form.addEventListener('submit', () => {
            const submit = form.querySelector('button[type="submit"]');
            if (submit) {
                submit.disabled = true;
                submit.setAttribute('aria-busy', 'true');
            }
        });
        syncReviewNotes();
    });

    document.querySelectorAll('form').forEach((form) => {
        form.querySelectorAll('[data-production-type-select]').forEach((select) => {
            const customInput = document.getElementById(select.dataset.customId || '');
            const customDetails = customInput?.closest('[data-production-custom-details]');
            if (!customInput || !customDetails) return;

            const syncCustomType = (focusInput = false) => {
                const otherSelected = select.value === '__other__';
                customDetails.open = otherSelected || customInput.value.trim() !== '';
                customInput.required = select.required && otherSelected;
                if (focusInput && otherSelected) customInput.focus();
            };

            select.addEventListener('change', () => syncCustomType(true));
            syncCustomType();
        });

        const stage = form.querySelector('[data-crop-stage]');
        const planted = form.querySelector('[data-crop-planted]');
        const harvested = form.querySelector('#crop-harvested');
        if (!stage || !planted || !harvested) return;

        const syncCropDates = () => {
            const planned = stage.value === 'planned';
            planted.min = planned ? planted.dataset.today : '';
            planted.max = planned ? '' : planted.dataset.today;
            harvested.required = stage.value === 'harvested';
            harvested.disabled = stage.value !== 'harvested';
            harvested.min = planted.value || '';
        };

        stage.addEventListener('change', syncCropDates);
        planted.addEventListener('change', syncCropDates);
        syncCropDates();
    });
});

document.addEventListener('DOMContentLoaded', () => {
    const header = document.querySelector('.public-agri-header');
    const toggle = header?.querySelector('[data-public-nav-toggle]');
    const navigation = header?.querySelector('[data-public-navigation]');

    if (header && toggle && navigation) {
        document.documentElement.classList.add('public-agri-js');
        const setMenuOpen = (open, restoreFocus = false) => {
            header.dataset.menuOpen = String(open);
            toggle.setAttribute('aria-expanded', String(open));
            toggle.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
            if (!open && restoreFocus) toggle.focus();
        };

        toggle.addEventListener('click', () => {
            const open = toggle.getAttribute('aria-expanded') !== 'true';
            setMenuOpen(open);
            if (open) navigation.querySelector('a')?.focus();
        });
        navigation.addEventListener('click', (event) => {
            if (event.target.closest('a')) setMenuOpen(false);
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && header.dataset.menuOpen === 'true') setMenuOpen(false, true);
        });
        document.addEventListener('click', (event) => {
            if (header.dataset.menuOpen === 'true' && !header.contains(event.target)) setMenuOpen(false);
        });
        window.addEventListener('resize', () => {
            if (window.innerWidth > 800) setMenuOpen(false);
        }, { passive: true });
    }

    const homeRevealTargets = [...document.querySelectorAll('[data-home-reveal]')];
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (homeRevealTargets.length && 'IntersectionObserver' in window && !prefersReducedMotion) {
        document.documentElement.classList.add('public-home-motion');
        const homeRevealObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -24px 0px' });

        homeRevealTargets.forEach((target) => homeRevealObserver.observe(target));
    }

    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        const input = document.getElementById(button.getAttribute('aria-controls'));
        if (!input) return;
        button.addEventListener('click', () => {
            const reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(reveal));
            button.textContent = reveal ? 'Hide password' : 'Show password';
            button.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
        });
    });

    document.querySelectorAll('form[data-registration-wizard]').forEach((form) => {
        const shell = form.closest('.public-auth-panel-register');
        if (!shell) return;
        const panels = [...form.querySelectorAll('[data-registration-panel]')];
        const indicators = [...shell.querySelectorAll('[data-registration-indicator]')];
        const status = shell.querySelector('[data-registration-status]');
        const back = form.querySelector('[data-registration-back]');
        const next = form.querySelector('[data-registration-next]');
        const submit = form.querySelector('[data-registration-submit]');
        if (!shell || panels.length !== 3 || indicators.length !== 3 || !status || !back || !next || !submit) return;

        const stepNames = ['Your details', 'Farm location', 'Password'];
        let step = Math.min(3, Math.max(1, Number(form.dataset.initialStep) || 1));
        form.noValidate = true;
        form.dataset.enhanced = 'true';

        const render = (focusHeading = false) => {
            panels.forEach((panel) => {
                panel.hidden = Number(panel.dataset.registrationPanel) !== step;
            });
            indicators.forEach((indicator) => {
                const number = Number(indicator.dataset.registrationIndicator);
                indicator.dataset.state = number < step ? 'complete' : (number === step ? 'current' : 'upcoming');
                if (number === step) indicator.setAttribute('aria-current', 'step');
                else indicator.removeAttribute('aria-current');
            });

            status.textContent = `Step ${step} of 3: ${stepNames[step - 1]}`;
            back.hidden = step === 1;
            next.hidden = step === 3;
            submit.hidden = step !== 3;

            if (step === 2) {
                window.requestAnimationFrame(() => panels[1].querySelector('[data-farm-location]')?.dispatchEvent(new Event('farm:visible')));
            }

            if (focusHeading) panels[step - 1].querySelector('[data-registration-heading]')?.focus();
        };

        const validatePanel = (panel) => {
            const fields = [...panel.querySelectorAll('input:not([type="hidden"]), select, textarea')]
                .filter((field) => !field.disabled);
            for (const field of fields) {
                if (field.checkValidity()) continue;
                field.setAttribute('aria-invalid', 'true');
                return field;
            }
            return null;
        };

        const password = form.querySelector('#password');
        const confirmation = form.querySelector('#password_confirmation');
        const syncPasswordConfirmation = () => {
            if (!password || !confirmation) return;
            confirmation.setCustomValidity(confirmation.value && confirmation.value !== password.value ? 'The passwords do not match.' : '');
        };

        [password, confirmation].filter(Boolean).forEach((field) => {
            field.addEventListener('input', () => {
                syncPasswordConfirmation();
                if (field.checkValidity()) field.removeAttribute('aria-invalid');
            });
        });

        form.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach((field) => {
            field.addEventListener('input', () => {
                if (field.checkValidity()) field.removeAttribute('aria-invalid');
            });
            field.addEventListener('change', () => {
                if (field.checkValidity()) field.removeAttribute('aria-invalid');
            });
        });

        next.addEventListener('click', () => {
            const invalidField = validatePanel(panels[step - 1]);
            if (invalidField) {
                invalidField.focus();
                invalidField.reportValidity();
                return;
            }
            step = Math.min(3, step + 1);
            render(true);
        });

        back.addEventListener('click', () => {
            step = Math.max(1, step - 1);
            render(true);
        });

        form.addEventListener('submit', (event) => {
            syncPasswordConfirmation();
            for (let index = 0; index < panels.length; index += 1) {
                const invalidField = validatePanel(panels[index]);
                if (!invalidField) continue;
                event.preventDefault();
                step = index + 1;
                render();
                invalidField.focus();
                invalidField.reportValidity();
                return;
            }
        });

        shell.querySelectorAll('#registration-errors a[href^="#"]').forEach((link) => {
            link.addEventListener('click', (event) => {
                const target = document.querySelector(link.getAttribute('href'));
                const targetPanel = target?.closest('[data-registration-panel]');
                if (!targetPanel) return;
                event.preventDefault();
                step = Number(targetPanel.dataset.registrationPanel);
                render();
                target.focus();
            });
        });

        render();
        shell.querySelector('#registration-errors')?.focus();
    });

    document.querySelectorAll('form[data-public-loading]').forEach((form) => {
        let submitting = false;
        form.addEventListener('submit', (event) => {
            if (event.defaultPrevented || submitting) return;
            submitting = true;
            const submit = form.querySelector('button[type="submit"]');
            if (!submit) return;
            submit.disabled = true;
            submit.setAttribute('aria-busy', 'true');
            if (submit.dataset.loadingLabel) submit.textContent = submit.dataset.loadingLabel;
        });
    });
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-farm-location]').forEach((section) => {
        const latitude = section.querySelector('[data-farm-latitude]');
        const longitude = section.querySelector('[data-farm-longitude]');
        const source = section.querySelector('[data-farm-location-source]');
        const accuracy = section.querySelector('[data-farm-location-accuracy]');
        const status = section.querySelector('[data-farm-location-status]');
        const locationSearchButton = section.querySelector('[data-farm-location-search]');
        const locationSearchLabel = section.querySelector('[data-farm-location-search-label]');
        const locationSearchStatus = section.querySelector('[data-farm-location-search-status]');
        const locationSearchResults = section.querySelector('[data-farm-location-search-results]');
        const mapLink = section.querySelector('[data-farm-map-link]');
        const useLocation = section.querySelector('[data-farm-use-location]');
        const clearPin = section.querySelector('[data-farm-clear-pin]');
        const mapElement = section.querySelector('[data-farm-map]');
        const boundaryInput = section.querySelector('[data-farm-boundary-coordinates]');
        const boundaryStatus = section.querySelector('[data-farm-boundary-status]');
        const boundaryEnabled = section.dataset.boundaryEnabled === 'true';
        const modeButtons = [...section.querySelectorAll('[data-farm-map-mode]')];
        const undoBoundary = section.querySelector('[data-farm-boundary-undo]');
        const saveBoundary = section.querySelector('[data-farm-boundary-save]');
        const clearBoundary = section.querySelector('[data-farm-boundary-clear]');
        const manualBoundaryControls = section.querySelector('[data-farm-boundary-manual-controls]');
        const manualBoundaryLatitude = section.querySelector('[data-farm-boundary-latitude]');
        const manualBoundaryLongitude = section.querySelector('[data-farm-boundary-longitude]');
        const addBoundaryPointButton = section.querySelector('[data-farm-boundary-add-point]');
        const form = section.closest('form');
        const farmArea = form?.elements.namedItem('area');
        const farmAreaUnit = form?.elements.namedItem('area_unit');
        const farmAreaFeedback = form?.querySelector('[data-farm-area-feedback]');
        let estimatedAreaSquareMeters = null;
        const locationDetails = form?.querySelector('[data-farm-location-details]');
        const farmBarangay = form?.querySelector('[data-farm-location-barangay]');
        const locationEnabled = section.dataset.locationEnabled === 'true';
        if (!latitude || !longitude || !source || !accuracy || !status || !useLocation) return;

        let municipalityBoundary = [];
        try {
            municipalityBoundary = JSON.parse(atob(mapElement?.dataset.bongabongBoundary || 'W10='));
        } catch {
            municipalityBoundary = [];
        }
        const containsMunicipality = (lat, lon) => {
            let inside = false;
            for (let i = 0, j = municipalityBoundary.length - 1; i < municipalityBoundary.length; j = i++) {
                const a = municipalityBoundary[i];
                const b = municipalityBoundary[j];
                if ((a.latitude > lat) !== (b.latitude > lat)
                    && lon < ((b.longitude - a.longitude) * (lat - a.latitude) / ((b.latitude - a.latitude) || 1e-12)) + a.longitude) {
                    inside = !inside;
                }
            }
            return inside;
        };

        const updateMapLink = () => {
            const lat = Number(latitude.value);
            const lon = Number(longitude.value);
            const valid = latitude.value !== '' && longitude.value !== '' && Number.isFinite(lat) && Number.isFinite(lon)
                && lat >= -90 && lat <= 90 && lon >= -180 && lon <= 180;
            if (mapLink) mapLink.hidden = !valid;
            if (valid) {
                if (mapLink) mapLink.href = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(`${lat},${lon}`)}`;
            }
        };

        const isValidPoint = (lat, lon) => Number.isFinite(lat) && Number.isFinite(lon)
            && lat >= -90 && lat <= 90 && lon >= -180 && lon <= 180;
        const readPoint = () => {
            if (latitude.value === '' || longitude.value === '') return null;
            const point = { lat: Number(latitude.value), lng: Number(longitude.value) };
            return isValidPoint(point.lat, point.lng) ? point : null;
        };
        let map = null;
        let marker = null;
        let mapsApi = null;
        let initializingMap = false;
        let outlineLayer = null;
        let mapMode = 'pin';
        let savedOutline = [];
        let draftOutline = [];
        try {
            savedOutline = boundaryInput?.value ? JSON.parse(boundaryInput.value) : [];
            if (!Array.isArray(savedOutline)) savedOutline = [];
        } catch {
            savedOutline = [];
        }
        const outlineLatLngs = (points) => points.map((point) => ({ lat: Number(point.latitude), lng: Number(point.longitude) }));
        const renderOutline = (points, draft = false) => {
            if (!map) return;
            if (outlineLayer) outlineLayer.setMap(null);
            outlineLayer = null;
            if (points.length < 2) return;
            outlineLayer = points.length >= 3
                ? new mapsApi.Polygon({ map, paths: outlineLatLngs(points),
                    strokeColor: draft ? '#d5ad55' : '#153d2b',
                    strokeWeight: 3,
                    strokeOpacity: 0.95,
                    fillColor: draft ? '#d5ad55' : '#26865c',
                    fillOpacity: draft ? 0.18 : 0.16,
                    clickable: false,
                })
                : new mapsApi.Polyline({ map, path: outlineLatLngs(points), strokeColor: '#d5ad55', strokeWeight: 3, clickable: false });
        };
        const updateBoundaryControls = () => {
            if (undoBoundary) undoBoundary.disabled = !boundaryEnabled || draftOutline.length === 0;
            if (saveBoundary) saveBoundary.disabled = !boundaryEnabled || draftOutline.length < 3;
            if (clearBoundary) clearBoundary.disabled = !boundaryEnabled || (savedOutline.length === 0 && draftOutline.length === 0);
        };
        const addBoundaryPoint = (lat, lon) => {
            if (!boundaryEnabled || !Number.isFinite(lat) || !Number.isFinite(lon) || !containsMunicipality(lat, lon)) {
                if (boundaryStatus) boundaryStatus.textContent = 'Enter a valid coordinate inside the mapped Bongabong outline.';
                return false;
            }
            draftOutline.push({ latitude: Number(lat.toFixed(7)), longitude: Number(lon.toFixed(7)) });
            renderOutline(draftOutline, true);
            if (boundaryStatus) boundaryStatus.textContent = `${draftOutline.length} outline point${draftOutline.length === 1 ? '' : 's'} added. Add at least three, then save the outline.`;
            updateBoundaryControls();
            return true;
        };
        const initializeMap = async () => {
            if (!mapElement || map || initializingMap) return map;
            const bounds = mapElement.getBoundingClientRect();
            if (bounds.width < 50 || bounds.height < 50) return null;
            initializingMap = true;
            try {
                if (!mapElement.dataset.googleKey) throw new Error('Google Maps key is not configured. Enter coordinates below.');
                mapsApi = await loadGoogleMaps(mapElement.dataset.googleKey);
                const savedPoint = readPoint();
                map = new mapsApi.Map(mapElement, { center: savedPoint || { lat: 12.74642, lng: 121.48741 }, zoom: savedPoint ? 17 : 15, mapTypeId: 'hybrid', mapTypeControl: true, streetViewControl: false });
                if (municipalityBoundary.length >= 3) new mapsApi.Polygon({ map, paths: outlineLatLngs(municipalityBoundary), strokeColor: '#26865c', strokeWeight: 2, fillOpacity: 0, clickable: false });
                if (savedPoint) setPin(savedPoint, source.value || 'manual', null, null, false);
                renderOutline(savedOutline);
                map.addListener('click', (event) => {
                    const point = { lat: event.latLng.lat(), lng: event.latLng.lng() };
                    if (mapMode === 'boundary') addBoundaryPoint(point.lat, point.lng);
                    else setPin(point);
                });
            } catch (error) {
                const mapMessage = section.querySelector('[data-farm-map-message]');
                if (mapMessage) {
                    mapMessage.hidden = false;
                    mapMessage.textContent = error.message || 'Google Maps could not load. GPS and coordinate entry can still be used.';
                }
            } finally { initializingMap = false; }
            return map;
        };
        const showMap = () => window.setTimeout(() => {
            initializeMap();
        }, 80);
        section.addEventListener('farm:visible', showMap);
        section.closest('details')?.addEventListener('toggle', (event) => {
            if (event.currentTarget.open) showMap();
        });

        const syncFormReview = () => form?.dispatchEvent(new Event('farm:coordinates-changed', { bubbles: true }));
        const setPin = (point, pinSource = 'manual', pinAccuracy = null, message = null, pan = true) => {
            if (!locationEnabled) {
                status.textContent = 'Map preview only. Pin saving will be available after the database update is activated.';
                return;
            }
            if (!point || !isValidPoint(point.lat, point.lng)) return;
            if (!containsMunicipality(point.lat, point.lng)) {
                status.textContent = 'That point appears outside the mapped Bongabong area. Check the farm barangay and choose a point within the outline.';
                const previousPoint = readPoint();
                if (marker && previousPoint) marker.setPosition(previousPoint);
                return;
            }
            latitude.value = point.lat.toFixed(7);
            longitude.value = point.lng.toFixed(7);
            source.value = pinSource;
            accuracy.value = pinSource === 'gps' && Number.isFinite(pinAccuracy) ? pinAccuracy.toFixed(2) : '';
            initializeMap();
            if (map) {
                if (!marker) {
                    marker = new mapsApi.Marker({ map, position: point, draggable: true, title: 'Farm location pin' });
                    marker.addListener('dragend', (event) => {
                        setPin({ lat: event.latLng.lat(), lng: event.latLng.lng() }, 'manual', null, 'Pin moved on the map. The MAO has not verified this location.', false);
                    });
                } else {
                    marker.setPosition(point);
                }
                if (pan) { map.setCenter(point); map.setZoom(Math.max(map.getZoom(), 16)); }
            }
            status.textContent = message || (pinSource === 'gps'
                ? `Location captured. Device accuracy is about ${Math.round(pinAccuracy)} m; the MAO has not verified this pin.`
                : 'Farm pin selected. The MAO has not verified this location.');
            updateMapLink();
            syncFormReview();
        };

        const applyOutlineEstimate = (points) => {
            const normalized = points.map((p) => ({ latitude: Number(p.latitude), longitude: Number(p.longitude) }));
            const area = farmOutlineSquareMeters(normalized);
            const center = farmOutlineInteriorPoint(normalized);
            if (!center || !containsMunicipality(center.lat, center.lng) || area <= 0) return;
            setPin(center, 'manual', null, 'Pin placed inside the farm outline. Save this form to keep changes.');
            if (!farmArea || !farmAreaUnit) return;
            estimatedAreaSquareMeters = area;
            if (farmAreaUnit.value === 'hectare' && area < 100) farmAreaUnit.value = 'square_meter';
            farmArea.value = (farmAreaUnit.value === 'hectare' ? area / 10000 : area).toFixed(2);
            farmArea.dispatchEvent(new Event('input', { bubbles: true }));
            if (farmAreaFeedback) farmAreaFeedback.textContent = `Estimated from the outline: ${farmArea.value} ${farmAreaUnit.value === 'hectare' ? 'hectares' : 'square meters'}. Save this form to keep it. Not an official land survey.`;
        };
        if (savedOutline.length >= 3 && farmArea && farmAreaUnit) applyOutlineEstimate(savedOutline);

        const clearLocationSearchResults = (message = '') => {
            if (locationSearchResults) {
                locationSearchResults.replaceChildren();
                locationSearchResults.hidden = true;
            }
            if (locationSearchStatus) locationSearchStatus.textContent = message;
        };

        const searchFarmLocation = async () => {
            const details = locationDetails?.value.trim() || '';
            const barangay = farmBarangay?.value || '';
            if (!details) {
                clearLocationSearchResults('Enter the farm sitio or a public nearby landmark first.');
                locationDetails?.focus();
                return;
            }
            if (!barangay) {
                clearLocationSearchResults('Choose the farm barangay before searching.');
                farmBarangay?.focus();
                return;
            }

            const csrfToken = form?.querySelector('input[name="_token"]')?.value;
            if (!csrfToken || !locationSearchButton || !locationSearchResults || !locationSearchStatus) {
                clearLocationSearchResults('Map search could not start. Refresh this page and try again.');
                return;
            }

            clearLocationSearchResults('Searching named places inside Bongabong…');
            locationSearchButton.disabled = true;
            locationSearchButton.setAttribute('aria-busy', 'true');
            if (locationSearchLabel) locationSearchLabel.textContent = 'Searching map…';

            const payload = new URLSearchParams({
                _token: csrfToken,
                location_details: details,
                barangay,
            });

            try {
                let response;
                try {
                    response = await fetch(section.dataset.locationSearchUrl, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        },
                        body: payload,
                        credentials: 'same-origin',
                    });
                } catch {
                    throw new Error('Could not reach map search. Check your connection, or place the pin directly on the map.');
                }
                let result;
                try {
                    result = await response.json();
                } catch {
                    throw new Error('Map search returned an unreadable response. Place the pin directly or try again later.');
                }
                if (!response.ok) throw new Error(result.message || 'Map search could not be completed. Try the map pin or coordinates instead.');

                if (!Array.isArray(result.results) || result.results.length === 0) {
                    locationSearchStatus.textContent = 'No matching named place was found inside Bongabong. Try another public landmark, or place the pin on the map.';
                    return;
                }

                locationSearchResults.replaceChildren();
                result.results.forEach((place, index) => {
                    const item = document.createElement('li');
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'ui-farm-location-result';
                    button.textContent = place.label;
                    button.setAttribute('aria-pressed', 'false');
                    button.addEventListener('click', () => {
                        locationSearchResults.querySelectorAll('button[aria-pressed="true"]').forEach((selected) => selected.setAttribute('aria-pressed', 'false'));
                        button.setAttribute('aria-pressed', 'true');
                        setPin(
                            { lat: Number(place.latitude), lng: Number(place.longitude) },
                            'manual',
                            null,
                            `Map moved to result ${index + 1}. Check the pin and adjust it to your farm location. This is not a land survey.`
                        );
                        locationSearchStatus.textContent = 'Suggested place selected. Confirm the marker is on your farm, then move it if needed.';
                    });
                    item.append(button);
                    locationSearchResults.append(item);
                });
                locationSearchResults.hidden = false;
                locationSearchResults.querySelector('button')?.click();
                locationSearchStatus.textContent = result.results.length > 1
                    ? 'Map moved to the first match. Check the pin, or choose another result below.'
                    : 'Map moved to the matching place. Check the pin and adjust it to your farm.';
            } catch (error) {
                locationSearchStatus.textContent = error instanceof Error
                    ? error.message
                    : 'Map search could not be completed. Place the pin directly, use GPS, or enter coordinates instead.';
            } finally {
                locationSearchButton.disabled = false;
                locationSearchButton.removeAttribute('aria-busy');
                if (locationSearchLabel) locationSearchLabel.textContent = 'Find this place on the map';
            }
        };

        locationSearchButton?.addEventListener('click', searchFarmLocation);
        locationDetails?.addEventListener('input', () => {
            clearLocationSearchResults(locationDetails.value.trim()
                ? 'Farm location text changed. Search again to move the pin to this place.'
                : 'Enter a public farm sitio or nearby landmark to search the map.');
        });
        farmBarangay?.addEventListener('change', () => {
            clearLocationSearchResults('Farm barangay changed. Search again to use the selected barangay.');
        });

        [latitude, longitude].forEach((field) => field.addEventListener('input', () => {
            if (latitude.value !== '' && longitude.value !== '') {
                const point = readPoint();
                if (point && containsMunicipality(point.lat, point.lng)) setPin(point, 'manual', null, 'Coordinates entered. The MAO has not verified this pin.');
                else if (point) status.textContent = 'Enter coordinates inside the mapped Bongabong area.';
                else {
                    if (marker && map) marker.setMap(null);
                    marker = null;
                    status.textContent = 'Enter valid latitude and longitude values.';
                }
            } else {
                source.value = '';
                accuracy.value = '';
                if (marker && map) marker.setMap(null);
                marker = null;
                status.textContent = 'Select the farm location on the map or enter both coordinates.';
            }
            updateMapLink();
        }));

        clearPin?.addEventListener('click', () => {
            latitude.value = '';
            longitude.value = '';
            source.value = '';
            accuracy.value = '';
            if (marker && map) marker.setMap(null);
            marker = null;
            status.textContent = 'Farm pin removed. The barangay and landmark are unchanged.';
            updateMapLink();
            syncFormReview();
        });

        modeButtons.forEach((button) => button.addEventListener('click', () => {
            const requestedMode = button.dataset.farmMapMode;
            if (requestedMode === 'boundary' && !boundaryEnabled) return;
            mapMode = requestedMode;
            modeButtons.forEach((candidate) => {
                const active = candidate === button;
                candidate.classList.toggle('is-selected', active);
                candidate.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            mapElement?.classList.toggle('is-drawing-boundary', mapMode === 'boundary');
            if (manualBoundaryControls) manualBoundaryControls.hidden = mapMode !== 'boundary';
            if (boundaryStatus) boundaryStatus.textContent = mapMode === 'boundary'
                ? 'Drawing mode is on. Click around the edge of your farm, moving in one direction.'
                : (savedOutline.length ? 'Saved farm outline is shown. Select Draw farm outline to replace it.' : 'Farm outline is optional. Use Draw farm outline to mark the approximate farm area.');
        }));
        undoBoundary?.addEventListener('click', () => {
            draftOutline.pop();
            renderOutline(draftOutline.length ? draftOutline : savedOutline, draftOutline.length > 0);
            if (boundaryStatus) boundaryStatus.textContent = `${draftOutline.length} draft point${draftOutline.length === 1 ? '' : 's'} remain.`;
            updateBoundaryControls();
        });
        saveBoundary?.addEventListener('click', () => {
            if (draftOutline.length < 3 || draftOutline.some((point) => !containsMunicipality(point.latitude, point.longitude))) {
                if (boundaryStatus) boundaryStatus.textContent = 'Add at least three points inside the Bongabong outline before saving.';
                return;
            }
            savedOutline = [...draftOutline];
            draftOutline = [];
            if (boundaryInput) boundaryInput.value = JSON.stringify(savedOutline);
            renderOutline(savedOutline);
            applyOutlineEstimate(savedOutline);
            if (boundaryStatus) boundaryStatus.textContent = 'Farm outline saved with this form. It is an approximate farmer-provided shape, not a land survey.';
            updateBoundaryControls();
        });
        clearBoundary?.addEventListener('click', () => {
            estimatedAreaSquareMeters = null;
            if (farmAreaFeedback) farmAreaFeedback.textContent = 'Outline removed. Check the farm area before saving.';
            savedOutline = [];
            draftOutline = [];
            if (boundaryInput) boundaryInput.value = '';
            if (outlineLayer && map) outlineLayer.setMap(null);
            outlineLayer = null;
            if (boundaryStatus) boundaryStatus.textContent = 'Farm outline cleared. The farm pin is unchanged.';
            updateBoundaryControls();
        });
        farmAreaUnit?.addEventListener('change', () => {
            if (estimatedAreaSquareMeters === null || !farmArea) return;
            const value = farmAreaUnit.value === 'hectare' ? estimatedAreaSquareMeters / 10000 : estimatedAreaSquareMeters;
            if (value < 0.01) {
                farmAreaUnit.value = 'square_meter';
                if (farmAreaFeedback) farmAreaFeedback.textContent = 'This outline is too small to display in hectares. Square meters are shown instead.';
                farmArea.value = Math.max(0.01, estimatedAreaSquareMeters).toFixed(2);
            } else {
                farmArea.value = value.toFixed(2);
                if (farmAreaFeedback) farmAreaFeedback.textContent = `Estimated from the outline: ${farmArea.value} ${farmAreaUnit.value === 'hectare' ? 'hectares' : 'square meters'}. Not an official land survey.`;
            }
            farmArea.dispatchEvent(new Event('input', { bubbles: true }));
        });
        farmArea?.addEventListener('input', (event) => {
            if (event.isTrusted) {
                estimatedAreaSquareMeters = null;
                if (farmAreaFeedback) farmAreaFeedback.textContent = 'Area entered manually. It may differ from the outline estimate.';
            }
        });
        addBoundaryPointButton?.addEventListener('click', () => {
            const latitudeValue = Number(manualBoundaryLatitude?.value);
            const longitudeValue = Number(manualBoundaryLongitude?.value);
            if (addBoundaryPoint(latitudeValue, longitudeValue)) {
                if (manualBoundaryLatitude) manualBoundaryLatitude.value = '';
                if (manualBoundaryLongitude) manualBoundaryLongitude.value = '';
                manualBoundaryLatitude?.focus();
            }
        });
        [manualBoundaryLatitude, manualBoundaryLongitude].filter(Boolean).forEach((field) => field.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                event.stopPropagation();
                addBoundaryPointButton?.click();
            }
        }));

        useLocation.addEventListener('click', () => {
            if (!window.isSecureContext || !navigator.geolocation) {
                status.textContent = 'Your browser cannot access device location on this connection. Enter coordinates manually or use a secure HTTPS connection.';
                return;
            }

            useLocation.disabled = true;
            status.textContent = 'Waiting for your device location permission…';
            navigator.geolocation.getCurrentPosition((position) => {
                setPin({ lat: position.coords.latitude, lng: position.coords.longitude }, 'gps', position.coords.accuracy,
                    `Location captured. Device accuracy is about ${Math.round(position.coords.accuracy)} m; the MAO has not verified this pin.`);
                useLocation.disabled = false;
            }, (error) => {
                status.textContent = error.code === error.PERMISSION_DENIED
                    ? 'Location permission was not granted. You can enter coordinates manually.'
                    : 'Could not get a location fix. Try again outdoors or enter coordinates manually.';
                useLocation.disabled = false;
            }, { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 });
        });

        updateMapLink();
    });

    document.querySelectorAll('[data-farm-wizard]').forEach((wizard) => {
        const form = wizard.querySelector('[data-farm-wizard-form]');
        const panels = [...wizard.querySelectorAll('[data-farm-step-panel]')];
        const indicators = [...wizard.querySelectorAll('[data-farm-step-indicator]')];
        const next = wizard.querySelector('[data-farm-next]');
        const back = wizard.querySelector('[data-farm-back]');
        const submit = wizard.querySelector('[data-farm-submit]');
        const status = wizard.querySelector('[data-farm-step-status]');
        const submitStatus = wizard.querySelector('[data-farm-submit-status]');
        if (!form || panels.length !== 4 || !next || !back || !submit) return;

        form.noValidate = true;
        let step = Math.min(4, Math.max(1, Number(wizard.dataset.initialStep) || 1));
        let submitting = false;
        const value = (name) => form.elements.namedItem(name)?.value?.trim() ?? '';
        const text = (name) => value(name) || 'Not entered';
        const setReview = () => {
            wizard.querySelector('[data-review-name]').textContent = text('name');
            wizard.querySelector('[data-review-barangay]').textContent = text('barangay');
            const latitude = value('latitude');
            const longitude = value('longitude');
            wizard.querySelector('[data-review-coordinates]').textContent = latitude && longitude ? `${latitude}, ${longitude}` : 'Not added';
            const area = value('area');
            const unit = form.elements.namedItem('area_unit')?.selectedOptions?.[0]?.textContent?.trim() ?? '';
            wizard.querySelector('[data-review-area]').textContent = area ? `${area} ${unit.toLowerCase()}` : 'Not entered';
            wizard.querySelector('[data-review-ownership]').textContent = form.elements.namedItem('ownership_type')?.selectedOptions?.[0]?.textContent?.trim() || 'Not entered';
            wizard.querySelector('[data-review-activities]').textContent = text('agricultural_activities');
            wizard.querySelector('[data-review-categories]').textContent = text('categories');
        };
        const render = (focus = false) => {
            panels.forEach((panel) => { panel.hidden = Number(panel.dataset.farmStepPanel) !== step; });
            indicators.forEach((item) => {
                const number = Number(item.dataset.farmStepIndicator);
                item.classList.toggle('is-current', number === step);
                item.classList.toggle('is-complete', number < step);
                if (number === step) item.setAttribute('aria-current', 'step');
                else item.removeAttribute('aria-current');
            });
            status.textContent = `Step ${step} of 4`;
            back.hidden = step === 1;
            next.hidden = step === 4;
            submit.hidden = step !== 4;
            if (step === 4) setReview();
            if (step === 2) {
                const mapElement = wizard.querySelector('[data-farm-map]');
                if (mapElement) window.requestAnimationFrame(() => mapElement.closest('[data-farm-location]')?.dispatchEvent(new Event('farm:visible')));
            }
            if (focus) wizard.querySelector(`[data-farm-step-panel="${step}"] [data-farm-step-heading]`)?.focus();
        };
        const validatePanel = (panel) => {
            const panelFields = [...panel.querySelectorAll('input:not([type=hidden]), select, textarea')];
            const invalid = panelFields.find((field) => !field.checkValidity());
            if (!invalid) return true;
            invalid.reportValidity();
            return false;
        };
        const moveNext = () => {
            const panel = panels.find((item) => Number(item.dataset.farmStepPanel) === step);
            if (!validatePanel(panel)) return;
            step = Math.min(4, step + 1);
            render(true);
        };

        next.addEventListener('click', moveNext);
        back.addEventListener('click', () => { step = Math.max(1, step - 1); render(true); });
        form.addEventListener('input', (event) => {
            if (event.target.matches('input, select, textarea')) setReview();
        });
        form.addEventListener('farm:coordinates-changed', setReview);
        form.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' || event.target.matches('textarea') || step === 4) return;
            event.preventDefault();
            moveNext();
        });
        form.addEventListener('submit', (event) => {
            if (submitting) { event.preventDefault(); return; }
            const invalidPanel = panels.find((panel) => [...panel.querySelectorAll('input:not([type=hidden]), select, textarea')].some((field) => !field.checkValidity()));
            if (invalidPanel) {
                event.preventDefault();
                step = Number(invalidPanel.dataset.farmStepPanel);
                render();
                const invalid = [...invalidPanel.querySelectorAll('input:not([type=hidden]), select, textarea')].find((field) => !field.checkValidity());
                invalid?.reportValidity();
                return;
            }
            submitting = true;
            submit.disabled = true;
            submitStatus.textContent = 'Saving your farm record…';
        });
        render();
    });

    document.querySelectorAll('[data-farm-wizard-open]').forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            const details = document.getElementById('farm-registration');
            if (!details) return;
            event.preventDefault();
            details.open = true;
            details.scrollIntoView({ behavior: 'smooth', block: 'start' });
            details.querySelector('summary')?.focus({ preventScroll: true });
        });
    });

    document.querySelectorAll('[data-ui-dialog-open]').forEach((trigger) => {
        trigger.addEventListener('click', () => document.getElementById(trigger.dataset.uiDialogOpen)?.showModal());
    });
    document.querySelectorAll('dialog[data-ui-dialog], dialog.ui-dialog').forEach((dialog) => {
        dialog.querySelectorAll('[data-ui-dialog-close]').forEach((button) => button.addEventListener('click', () => dialog.close()));
        dialog.querySelector('[data-ui-dialog-confirm]')?.addEventListener('click', () => {
            dialog.dispatchEvent(new CustomEvent('ui:confirm', { bubbles: true, detail: { dialog } }));
            dialog.close();
        });
        dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
    });
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-requirement-editor]').forEach((editor) => {
        const list = editor.querySelector('[data-requirement-list]');
        const template = editor.querySelector('template[data-requirement-template]');
        const empty = editor.querySelector('[data-requirement-empty]');
        if (!list || !template) return;

        const currentIndexes = [...editor.querySelectorAll('[name^="requirements["]')]
            .map((field) => Number(field.name.match(/^requirements\[(\d+)\]/)?.[1]))
            .filter(Number.isFinite);
        let nextIndex = currentIndexes.length ? Math.max(...currentIndexes) + 1 : 0;
        const syncEmpty = () => { if (empty) empty.hidden = list.children.length > 0; };

        editor.addEventListener('click', (event) => {
            const add = event.target.closest('[data-requirement-add]');
            if (add) {
                const container = document.createElement('div');
                container.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(nextIndex++));
                const row = container.firstElementChild;
                if (row) {
                    list.append(row);
                    syncEmpty();
                    row.querySelector('input[name$="[display_name]"]')?.focus();
                }
                return;
            }

            const remove = event.target.closest('[data-requirement-remove]');
            if (!remove) return;
            remove.closest('[data-requirement-row]')?.remove();
            syncEmpty();
            editor.querySelector('[data-requirement-add]')?.focus();
        });

        syncEmpty();
    });
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-ffrms-map]').forEach((mapElement) => {
        const emptyMessage = mapElement.parentElement?.querySelector('[data-ffrms-map-empty]');
        const mapMessage = mapElement.parentElement?.querySelector('[data-ffrms-map-message]');
        let points = [];

        try {
            points = JSON.parse(mapElement.dataset.ffrmsMapPoints || '[]');
        } catch {
            if (emptyMessage) {
                emptyMessage.hidden = false;
                emptyMessage.textContent = 'Farm map data could not be loaded. Refresh the page and try again.';
            }
        }

        if (!Array.isArray(points)) points = [];
        let outline = [];
        try {
            const parsed = JSON.parse(mapElement.dataset.ffrmsMapOutline || '[]');
            if (Array.isArray(parsed) && parsed.length >= 3 && parsed.length <= 80 && parsed.every((p) => Number.isFinite(Number(p.latitude)) && Number.isFinite(Number(p.longitude)) && Math.abs(Number(p.latitude)) <= 90 && Math.abs(Number(p.longitude)) <= 180)) {
                outline = parsed.map((p) => [Number(p.latitude), Number(p.longitude)]);
            }
        } catch { outline = []; }
        points = points.filter((point) => Number.isFinite(Number(point.latitude))
            && Number.isFinite(Number(point.longitude))
            && Number(point.latitude) >= -90 && Number(point.latitude) <= 90
            && Number(point.longitude) >= -180 && Number(point.longitude) <= 180);

        if (emptyMessage) emptyMessage.hidden = points.length > 0 || outline.length > 0;

        const renderGoogleMap = async () => {
            mapElement.setAttribute('aria-busy', 'true');
            if (!mapElement.dataset.googleKey) {
                throw new Error('Google Maps is not configured for this application. Farm records remain available in the registration details.');
            }

            const mapsApi = await loadGoogleMaps(mapElement.dataset.googleKey);
            const map = new mapsApi.Map(mapElement, {
                center: { lat: 12.74642, lng: 121.48741 },
                zoom: 13,
                mapTypeId: mapsApi.MapTypeId.HYBRID,
                mapTypeControl: true,
                mapTypeControlOptions: { style: mapsApi.MapTypeControlStyle.DROPDOWN_MENU },
                fullscreenControl: true,
                streetViewControl: false,
                zoomControl: true,
                gestureHandling: 'cooperative',
            });
            const bounds = new mapsApi.LatLngBounds();
            const mapPoints = [];

            if (outline.length >= 3) {
                const outlinePath = outline.map(([latitude, longitude]) => ({ lat: latitude, lng: longitude }));
                new mapsApi.Polygon({
                    map,
                    paths: outlinePath,
                    strokeColor: '#153d2b',
                    strokeOpacity: 0.95,
                    strokeWeight: 3,
                    fillColor: '#26865c',
                    fillOpacity: 0.2,
                    clickable: false,
                });
                outlinePath.forEach((point) => bounds.extend(point));
            }

            points.forEach((point) => {
                const position = { lat: Number(point.latitude), lng: Number(point.longitude) };
                const marker = new mapsApi.Marker({
                    map,
                    position,
                    title: `${point.farm || 'Farm'} location`,
                });
                const content = document.createElement('div');
                const farmName = document.createElement('strong');
                const farmerName = document.createElement('div');
                const barangay = document.createElement('small');
                farmName.textContent = point.farm || 'Registered farm';
                farmerName.textContent = `${point.farmer || 'Farmer'}${point.farmerCode ? ` / ${point.farmerCode}` : ''}`;
                barangay.textContent = point.barangay || 'Barangay not recorded';
                content.append(farmName, farmerName, barangay);

                const infoWindow = new mapsApi.InfoWindow({ content });
                marker.addListener('click', () => infoWindow.open({ map, anchor: marker }));
                bounds.extend(position);
                mapPoints.push(position);
            });

            if (!bounds.isEmpty()) {
                map.fitBounds(bounds, 36);
                if (mapPoints.length === 1 && outline.length === 0) {
                    map.setCenter(mapPoints[0]);
                    map.setZoom(16);
                }
            }

            if (mapMessage) {
                mapMessage.classList.remove('is-error');
                mapMessage.textContent = points.length || outline.length
                    ? 'Google Maps loaded. Farm pins and any saved outline are shown on the satellite map.'
                    : 'Google Maps loaded. No farm pins are recorded for this view yet.';
            }
            mapElement.setAttribute('aria-busy', 'false');
        };

        renderGoogleMap().catch((error) => {
            mapElement.setAttribute('aria-busy', 'false');
            if (mapMessage) {
                mapMessage.classList.add('is-error');
                mapMessage.textContent = error.message || 'Google Maps could not load. Farm records remain available in the registration details.';
            }
        });
    });
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-open-disclosure]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const disclosure = document.getElementById(trigger.dataset.openDisclosure);
            if (!disclosure) return;
            disclosure.open = true;
            window.setTimeout(() => disclosure.querySelector('summary')?.focus({ preventScroll: true }), 0);
        });
    });

    document.querySelectorAll('form[data-submit-feedback]').forEach((form) => {
        form.addEventListener('submit', () => {
            if (!form.checkValidity()) return;
            const submit = form.querySelector('button[type="submit"]');
            const label = form.querySelector('[data-submit-label]');
            if (!submit || submit.disabled) return;
            submit.disabled = true;
            submit.setAttribute('aria-busy', 'true');
            if (label) label.textContent = 'Sending request…';
        });
    });
});
