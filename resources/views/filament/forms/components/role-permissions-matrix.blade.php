@php
    $statePath = $getStatePath();
    $fieldWrapperView = $getFieldWrapperView();
    $isSystemRole = $isSystemRole();
    $defaultPermissions = $getDefaultPermissions();
    $groupedPermissions = $getGroupedPermissions();
    $allPermissions = $getAllPermissions();
    $roleRecord = $getRoleRecord();
    $totalCount = count($allPermissions);
    $firstCatId = array_key_first($groupedPermissions) ?? 'supply';
@endphp

<x-dynamic-component
    :component="$fieldWrapperView"
    :field="$field"
>
    <style>
        /* Scoped styles for Role Permissions Matrix */
        .rpm-container {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            width: 100%;
            box-sizing: border-box;
        }

        /* Top Controls Bar */
        .rpm-top-bar {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            box-sizing: border-box;
        }

        /* Sidebar Module Button - Guaranteed 100% full width row */
        .rpm-module-btn {
            width: 100% !important;
            box-sizing: border-box !important;
            display: flex !important;
            flex-direction: row !important;
            align-items: center !important;
            justify-content: space-between !important;
            padding: 0.55rem 0.75rem !important;
            border-radius: 0.5rem !important;
            font-size: 0.8125rem !important;
            cursor: pointer !important;
            text-align: left !important;
            transition: background-color 0.15s ease, color 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease !important;
            border: 1px solid transparent !important;
            user-select: none !important;
            -webkit-user-select: none !important;
            background-color: transparent;
            color: #334155;
            font-weight: 500;
        }
        .rpm-module-btn:hover {
            background-color: #f1f5f9;
        }
        .rpm-module-btn.is-active {
            background-color: #2563eb !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            border-color: #2563eb !important;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.25) !important;
        }

        .rpm-module-btn-label {
            display: flex !important;
            align-items: center !important;
            gap: 0.5rem !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            white-space: nowrap !important;
            pointer-events: none !important;
            flex: 1 !important;
            min-width: 0 !important;
        }
        .rpm-module-emoji {
            font-size: 1rem !important;
            flex-shrink: 0 !important;
            line-height: 1 !important;
        }
        .rpm-module-name {
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            white-space: nowrap !important;
            font-size: 0.8125rem !important;
        }

        /* Sidebar Count Badges */
        .rpm-module-count-badge {
            font-size: 0.6875rem !important;
            font-weight: 700 !important;
            padding: 0.125rem 0.45rem !important;
            border-radius: 9999px !important;
            flex-shrink: 0 !important;
            margin-left: 0.5rem !important;
            pointer-events: none !important;
            background-color: #f1f5f9;
            color: #94a3b8;
            transition: all 0.15s ease;
        }
        .rpm-module-count-badge.has-selected {
            background-color: #dbeafe;
            color: #1d4ed8;
        }
        .rpm-module-btn.is-active .rpm-module-count-badge {
            background-color: rgba(255, 255, 255, 0.25) !important;
            color: #ffffff !important;
        }

        /* Permission Cards */
        .rpm-card {
            border-radius: 0.5rem !important;
            padding: 0.65rem 0.75rem !important;
            cursor: pointer !important;
            transition: border-color 0.15s ease, background-color 0.15s ease, box-shadow 0.15s ease !important;
            display: flex !important;
            flex-direction: column !important;
            justify-content: space-between !important;
            gap: 0.45rem !important;
            user-select: none !important;
            -webkit-user-select: none !important;
            border: 1.5px solid #e2e8f0 !important;
            background-color: #ffffff !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02) !important;
            box-sizing: border-box !important;
        }
        .rpm-card:hover {
            border-color: #94a3b8 !important;
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.06) !important;
        }
        .rpm-card.is-selected {
            border-color: #2563eb !important;
            background-color: #eff6ff !important;
            box-shadow: 0 0 0 1px rgba(37, 99, 235, 0.15), 0 2px 4px rgba(0, 0, 0, 0.03) !important;
        }

        /* Crucial: All children inside the card inherit pointer cursor and cannot steal mouse pointer events */
        .rpm-card * {
            cursor: pointer !important;
            pointer-events: none !important;
            user-select: none !important;
            -webkit-user-select: none !important;
        }

        /* Distinct High-Contrast Checkbox: clearly visible blank box when unselected */
        .rpm-checkbox {
            width: 1.25rem !important;
            height: 1.25rem !important;
            min-width: 1.25rem !important;
            min-height: 1.25rem !important;
            border-radius: 0.35rem !important;
            border: 2px solid #94a3b8 !important;
            background-color: #ffffff !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            flex-shrink: 0 !important;
            margin-top: 0.05rem !important;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.06) !important;
            transition: all 0.15s ease-in-out !important;
            box-sizing: border-box !important;
        }
        .rpm-card:hover .rpm-checkbox:not(.is-checked) {
            border-color: #64748b !important;
            background-color: #f8fafc !important;
        }
        .rpm-checkbox.is-checked {
            background-color: #2563eb !important;
            border-color: #2563eb !important;
            box-shadow: 0 1px 3px rgba(37, 99, 235, 0.3) !important;
        }

        /* Category active count pill */
        .rpm-cat-active-pill {
            font-size: 0.75rem !important;
            font-weight: 700 !important;
            padding: 0.2rem 0.55rem !important;
            border-radius: 9999px !important;
            background-color: #f1f5f9;
            color: #64748b;
            transition: all 0.15s ease;
        }
        .rpm-cat-active-pill.has-active {
            background-color: #dbeafe !important;
            color: #1e40af !important;
        }

        /* Progress Bar */
        .rpm-progress-bar-fill {
            height: 100% !important;
            background-color: #2563eb !important;
            border-radius: 9999px !important;
            transition: width 0.2s ease !important;
        }
    </style>

    <div
        class="rpm-container"
        x-data="{
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            search: '',
            activeCat: '{{ $firstCatId }}',
            defaults: @js($defaultPermissions),
            allKeys: @js(array_keys($allPermissions)),

            isPermissionSelected(key) {
                return Array.isArray(this.state) && this.state.includes(key);
            },

            togglePermission(key) {
                if (!Array.isArray(this.state)) {
                    this.state = [];
                }
                if (this.state.includes(key)) {
                    this.state = this.state.filter(p => p !== key);
                } else {
                    this.state = [...this.state, key];
                }
            },

            selectAll() {
                this.state = [...this.allKeys];
            },

            deselectAll() {
                this.state = [];
            },

            resetToDefaults() {
                if (confirm('Revert all permissions for {{ $roleRecord?->display_name_or_name ?? 'this system role' }} to factory system defaults? Any custom modifications will be replaced.')) {
                    this.state = [...this.defaults];
                }
            },

            isCategoryAllSelected(keys) {
                if (!Array.isArray(this.state) || keys.length === 0) return false;
                return keys.every(k => this.state.includes(k));
            },

            toggleCategory(keys) {
                if (!Array.isArray(this.state)) {
                    this.state = [];
                }
                if (this.isCategoryAllSelected(keys)) {
                    const toRemove = new Set(keys);
                    this.state = this.state.filter(k => !toRemove.has(k));
                } else {
                    const combined = new Set([...this.state, ...keys]);
                    this.state = Array.from(combined);
                }
            },

            getCategorySelectedCount(keys) {
                if (!Array.isArray(this.state)) return 0;
                return keys.filter(k => this.state.includes(k)).length;
            },

            matchesSearch(label, code, description) {
                if (!this.search || !this.search.trim()) return true;
                const q = this.search.toLowerCase().trim();
                return (label && label.toLowerCase().includes(q))
                    || (code && code.toLowerCase().includes(q))
                    || (description && description.toLowerCase().includes(q));
            },

            categoryHasMatches(permissions) {
                if (!this.search || !this.search.trim()) return true;
                return Object.values(permissions).some(p => this.matchesSearch(p.label, p.code, p.description));
            },

            getSearchMatchesCount() {
                if (!this.search || !this.search.trim()) return this.allKeys.length;
                let count = 0;
                const all = @js($allPermissions);
                for (const code in all) {
                    const p = all[code];
                    if (this.matchesSearch(p.label, p.code, p.description)) {
                        count++;
                    }
                }
                return count;
            }
        }"
    >
        <!-- Top Controls Bar -->
        <div class="rpm-top-bar">
            <!-- Search Field -->
            <div style="position: relative; flex: 1; min-width: 260px;">
                <span style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: #94a3b8; pointer-events: none; display: flex; align-items: center;">
                    <svg xmlns="http://www.w3.org/2000/svg" style="width: 1.125rem; height: 1.125rem;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </span>
                <input
                    type="search"
                    x-model="search"
                    placeholder="Search permissions across all modules (e.g. bank, payout, delete, audit)..."
                    style="width: 100%; padding: 0.45rem 0.75rem 0.45rem 2.35rem; font-size: 0.8125rem; border: 1px solid #cbd5e1; border-radius: 0.5rem; outline: none; background: #f8fafc; transition: all 0.15s; box-sizing: border-box;"
                    onfocus="this.style.borderColor='#3b82f6'; this.style.background='#ffffff';"
                    onblur="this.style.borderColor='#cbd5e1'; this.style.background='#f8fafc';"
                />
            </div>

            <!-- Global Action Buttons -->
            <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                <button
                    type="button"
                    x-on:click="selectAll()"
                    style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.375rem 0.65rem; font-size: 0.75rem; font-weight: 600; color: #334155; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 0.375rem; cursor: pointer; transition: all 0.15s;"
                    onmouseover="this.style.background='#f1f5f9';"
                    onmouseout="this.style.background='#f8fafc';"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" style="width: 0.875rem; height: 0.875rem; color: #059669;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    Select All
                </button>

                <button
                    type="button"
                    x-on:click="deselectAll()"
                    style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.375rem 0.65rem; font-size: 0.75rem; font-weight: 600; color: #64748b; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 0.375rem; cursor: pointer; transition: all 0.15s;"
                    onmouseover="this.style.background='#f8fafc';"
                    onmouseout="this.style.background='#ffffff';"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" style="width: 0.875rem; height: 0.875rem; color: #ef4444;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    Deselect All
                </button>

                @if ($isSystemRole)
                    <button
                        type="button"
                        x-on:click="resetToDefaults()"
                        title="Restore factory default permissions configured for this core system role"
                        style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.375rem 0.75rem; font-size: 0.75rem; font-weight: 700; color: #92400e; background: #fffbeb; border: 1px solid #fde68a; border-radius: 0.375rem; cursor: pointer; transition: all 0.15s;"
                        onmouseover="this.style.background='#fef3c7';"
                        onmouseout="this.style.background='#fffbeb';"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" style="width: 0.875rem; height: 0.875rem; color: #b45309;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span>Reset Defaults</span>
                    </button>
                @endif

                <!-- Total Count Indicator -->
                <div style="display: flex; align-items: center; gap: 0.5rem; padding-left: 0.75rem; border-left: 1px solid #e2e8f0;">
                    <div style="display: flex; flex-direction: column; align-items: flex-end;">
                        <span style="font-size: 0.875rem; font-weight: 700; color: #0f172a; line-height: 1;">
                            <span style="color: #2563eb;" x-text="Array.isArray(state) ? state.length : 0"></span>
                            <span style="color: #94a3b8; font-size: 0.75rem; font-weight: 500;">/ {{ $totalCount }}</span>
                        </span>
                        <span style="font-size: 0.65rem; font-weight: 600; color: #64748b; text-transform: uppercase;">Assigned</span>
                    </div>

                    <div style="width: 48px; height: 6px; background: #e2e8f0; border-radius: 9999px; overflow: hidden;">
                        <div
                            class="rpm-progress-bar-fill"
                            :style="'width: ' + Math.min(100, Math.round(((Array.isArray(state) ? state.length : 0) / {{ max(1, $totalCount) }}) * 100)) + '%'"
                        ></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search Active Banner -->
        <div
            x-show="search.trim() !== ''"
            style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 0.5rem; padding: 0.5rem 0.875rem; display: flex; align-items: center; justify-content: space-between; font-size: 0.8125rem; color: #1e40af;"
        >
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span>🔍</span>
                <span>
                    Found <strong x-text="getSearchMatchesCount()"></strong> permissions matching "<strong x-text="search"></strong>":
                </span>
            </div>
            <button
                type="button"
                x-on:click="search = ''"
                style="background: none; border: none; font-size: 0.75rem; font-weight: 700; color: #2563eb; cursor: pointer; text-decoration: underline;"
            >
                Clear Search & Show Modules
            </button>
        </div>

        <!-- Master-Detail Layout: Left Sidebar + Right Permissions Content -->
        <div style="display: flex; gap: 1.25rem; align-items: flex-start; width: 100%;">
            <!-- Left Sidebar: Domain Module List -->
            <div
                x-show="search.trim() === ''"
                style="width: 250px; flex-shrink: 0; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 0.5rem; display: flex; flex-direction: column; gap: 0.25rem; box-shadow: 0 1px 2px rgba(0,0,0,0.03); box-sizing: border-box;"
            >
                <div style="padding: 0.35rem 0.5rem; font-size: 0.6875rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; user-select: none;">
                    Functional Modules
                </div>

                <!-- All Modules Option -->
                <button
                    type="button"
                    class="rpm-module-btn"
                    :class="{ 'is-active': activeCat === 'all' }"
                    x-on:click="activeCat = 'all'"
                >
                    <div class="rpm-module-btn-label">
                        <span class="rpm-module-emoji">🌐</span>
                        <span class="rpm-module-name">All Modules</span>
                    </div>
                    <span
                        class="rpm-module-count-badge"
                        :class="{ 'is-active': activeCat === 'all', 'has-selected': Array.isArray(state) && state.length > 0 }"
                        x-text="(Array.isArray(state) ? state.length : 0) + '/{{ $totalCount }}'"
                    ></span>
                </button>

                <div style="height: 1px; background: #f1f5f9; margin: 0.25rem 0;"></div>

                <!-- 13 Module Buttons: Each is guaranteed to span full width and highlight edge-to-edge -->
                @foreach ($groupedPermissions as $catId => $catGroup)
                    @php
                        $category = $catGroup['category'];
                        $permissions = $catGroup['permissions'];
                        $catKeys = array_keys($permissions);
                        $catCount = count($permissions);
                        $emoji = match ($catId) {
                            'supply' => '🎯',
                            'mou' => '📄',
                            'property' => '🏢',
                            'banking' => '💳',
                            'leasing' => '🔑',
                            'audits' => '📋',
                            'maintenance' => '🔧',
                            'deboarding' => '🚪',
                            'billing' => '🧾',
                            'payout' => '💵',
                            'accounting' => '⚖️',
                            'admin' => '🛡️',
                            'navigation' => '⚡',
                            default => '✨',
                        };
                    @endphp

                    <button
                        type="button"
                        class="rpm-module-btn"
                        :class="{ 'is-active': activeCat === '{{ $catId }}' }"
                        x-on:click="activeCat = '{{ $catId }}'"
                    >
                        <div class="rpm-module-btn-label">
                            <span class="rpm-module-emoji">{{ $emoji }}</span>
                            <span class="rpm-module-name">{{ $category['label'] }}</span>
                        </div>

                        <span
                            class="rpm-module-count-badge"
                            :class="{
                                'is-active': activeCat === '{{ $catId }}',
                                'has-selected': getCategorySelectedCount(@js($catKeys)) > 0
                            }"
                            x-text="getCategorySelectedCount(@js($catKeys)) + '/{{ $catCount }}'"
                        ></span>
                    </button>
                @endforeach
            </div>

            <!-- Right Content: Permissions Cards Area -->
            <div style="flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1rem;">
                @foreach ($groupedPermissions as $catId => $catGroup)
                    @php
                        $category = $catGroup['category'];
                        $permissions = $catGroup['permissions'];
                        $catKeys = array_keys($permissions);
                        $catCount = count($permissions);
                        $emoji = match ($catId) {
                            'supply' => '🎯',
                            'mou' => '📄',
                            'property' => '🏢',
                            'banking' => '💳',
                            'leasing' => '🔑',
                            'audits' => '📋',
                            'maintenance' => '🔧',
                            'deboarding' => '🚪',
                            'billing' => '🧾',
                            'payout' => '💵',
                            'accounting' => '⚖️',
                            'admin' => '🛡️',
                            'navigation' => '⚡',
                            default => '✨',
                        };
                    @endphp

                    <div
                        x-show="search.trim() !== '' ? categoryHasMatches(@js($permissions)) : (activeCat === 'all' || activeCat === '{{ $catId }}')"
                        style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; overflow: hidden; box-shadow: 0 1px 2px rgba(0,0,0,0.03);"
                    >
                        <!-- Category Header Bar -->
                        <div
                            style="padding: 0.75rem 1rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap;"
                        >
                            <div style="display: flex; align-items: center; gap: 0.625rem;">
                                <span style="font-size: 1.25rem;">{{ $emoji }}</span>
                                <div>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <h4 style="margin: 0; font-size: 0.9375rem; font-weight: 700; color: #0f172a;">
                                            {{ $category['label'] }}
                                        </h4>
                                        <span style="font-size: 0.6875rem; font-weight: 600; color: #64748b; background: #e2e8f0; padding: 0.125rem 0.4rem; border-radius: 9999px;">
                                            {{ $catCount }} permissions
                                        </span>
                                    </div>
                                    <p style="margin: 0.125rem 0 0 0; font-size: 0.75rem; color: #64748b; line-height: 1.3;">
                                        {{ $category['description'] }}
                                    </p>
                                </div>
                            </div>

                            <!-- Right Category Quick Toggles -->
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <span
                                    class="rpm-cat-active-pill"
                                    :class="{ 'has-active': getCategorySelectedCount(@js($catKeys)) > 0 }"
                                >
                                    <span x-text="getCategorySelectedCount(@js($catKeys))"></span> / {{ $catCount }} Active
                                </span>

                                <button
                                    type="button"
                                    x-on:click="toggleCategory(@js($catKeys))"
                                    style="font-size: 0.75rem; font-weight: 600; padding: 0.25rem 0.6rem; border-radius: 0.375rem; border: 1px solid #cbd5e1; background: #ffffff; color: #1e293b; cursor: pointer; transition: all 0.15s;"
                                    onmouseover="this.style.background='#f1f5f9';"
                                    onmouseout="this.style.background='#ffffff';"
                                >
                                    <span x-text="isCategoryAllSelected(@js($catKeys)) ? 'Deselect Category' : 'Select Category'"></span>
                                </button>
                            </div>
                        </div>

                        <!-- Permissions Grid for This Category -->
                        <div
                            style="padding: 0.875rem; display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 0.625rem;"
                        >
                            @foreach ($permissions as $code => $perm)
                                @php
                                    $risk = $perm['risk'] ?? 'action';
                                    $riskBadge = match ($risk) {
                                        'read' => ['bg' => '#f1f5f9', 'text' => '#475569', 'border' => '#e2e8f0', 'label' => 'Read-Only'],
                                        'sensitive' => ['bg' => '#fdf4ff', 'text' => '#86198f', 'border' => '#f5d0fe', 'label' => 'Sensitive'],
                                        'fiduciary' => ['bg' => '#fef3c7', 'text' => '#92400e', 'border' => '#fde68a', 'label' => 'Fiduciary'],
                                        'destructive' => ['bg' => '#fee2e2', 'text' => '#991b1b', 'border' => '#fecaca', 'label' => 'Destructive'],
                                        default => ['bg' => '#eff6ff', 'text' => '#1d4ed8', 'border' => '#bfdbfe', 'label' => 'Action'],
                                    };
                                @endphp

                                <div
                                    x-show="matchesSearch(@js($perm['label']), @js($code), @js($perm['description']))"
                                    x-on:click="togglePermission('{{ $code }}')"
                                    class="rpm-card"
                                    :class="{ 'is-selected': isPermissionSelected('{{ $code }}') }"
                                >
                                    <!-- Top Row: Checkbox, Title & Risk Badge -->
                                    <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 0.375rem;">
                                        <div style="display: flex; align-items: flex-start; gap: 0.5rem;">
                                            <!-- High-Contrast Checkbox: crisp blank square when unselected, blue check when selected -->
                                            <div
                                                class="rpm-checkbox"
                                                :class="{ 'is-checked': isPermissionSelected('{{ $code }}') }"
                                            >
                                                <svg
                                                    x-show="isPermissionSelected('{{ $code }}')"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    style="width: 0.8125rem; height: 0.8125rem; display: block;"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke="#ffffff"
                                                    stroke-width="3.5"
                                                >
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </div>

                                            <!-- Human Readable Title -->
                                            <div style="font-size: 0.8125rem; font-weight: 600; color: #0f172a; line-height: 1.25;">
                                                {{ $perm['label'] }}
                                            </div>
                                        </div>

                                        <!-- Risk Badge -->
                                        <span
                                            style="font-size: 0.625rem; font-weight: 700; padding: 0.075rem 0.35rem; border-radius: 9999px; background: {{ $riskBadge['bg'] }}; color: {{ $riskBadge['text'] }}; border: 1px solid {{ $riskBadge['border'] }}; flex-shrink: 0;"
                                        >
                                            {{ $riskBadge['label'] }}
                                        </span>
                                    </div>

                                    <!-- Middle Row: Plain English Description -->
                                    <p style="margin: 0; font-size: 0.725rem; color: #475569; line-height: 1.35;">
                                        {{ $perm['description'] }}
                                    </p>

                                    <!-- Bottom Row: Machine Code & Active Indicator -->
                                    <div style="display: flex; align-items: center; justify-content: space-between; border-top: 1px dashed #e2e8f0; padding-top: 0.3rem; margin-top: 0.125rem;">
                                        <span style="font-family: monospace; font-size: 0.65rem; color: #64748b; background: #f8fafc; border: 1px solid #e2e8f0; padding: 0.05rem 0.25rem; border-radius: 0.25rem;">
                                            {{ $code }}
                                        </span>

                                        <span
                                            x-show="isPermissionSelected('{{ $code }}')"
                                            style="font-size: 0.65rem; font-weight: 700; color: #2563eb; display: inline-flex; align-items: center; gap: 0.15rem;"
                                        >
                                            ✓ Active
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-dynamic-component>
