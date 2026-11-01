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
