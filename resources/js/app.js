document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('app-sidebar');
    const shell = document.querySelector('.erp-shell');
    const sidebarToggle = document.getElementById('mobile-sidebar-toggle');
    const desktopSidebarToggle = document.getElementById('desktop-sidebar-toggle');
    const backdrop = document.getElementById('mobile-sidebar-backdrop');

    const closeSidebar = () => {
        sidebar?.classList.remove('is-open');
        sidebarToggle?.setAttribute('aria-expanded', 'false');
        if (backdrop) backdrop.hidden = true;
    };

    sidebarToggle?.addEventListener('click', () => {
        const open = sidebar?.classList.toggle('is-open') ?? false;
        sidebarToggle.setAttribute('aria-expanded', String(open));
        if (backdrop) backdrop.hidden = !open;
    });
    backdrop?.addEventListener('click', closeSidebar);
    sidebar?.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeSidebar));
    window.addEventListener('resize', () => { if (window.innerWidth >= 1024) closeSidebar(); });

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
        if (event.key !== 'Escape') return;

        document.querySelectorAll('[data-topbar-menu]').forEach((menu) => { menu.hidden = true; });
        document.querySelectorAll('[data-menu-toggle]').forEach((toggle) => { toggle.setAttribute('aria-expanded', 'false'); });
    });

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('form[data-confirm-submit]');
        if (form && !window.confirm(form.dataset.confirmSubmit)) event.preventDefault();
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
});
