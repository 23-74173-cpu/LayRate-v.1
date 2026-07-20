@extends('layouts.app')
@section('title', 'Profile')

@section('content')
<div class="space-y-5">

    <x-page-header title="Profile" subtitle="Manage your account and security settings" />

    {{-- Tabs --}}
    <div id="profile-tabs-nav">
        <x-underline-tabs :tabs="[
            'profile'  => ['label' => 'Profile',         'icon' => 'user',     'onclick' => 'switchProfileTab(\'profile\')'],
            'settings' => ['label' => 'Settings', 'icon' => 'settings', 'onclick' => 'switchProfileTab(\'settings\')'],
        ]" active="{{ $tab }}" />
    </div>

    <div class="max-w-2xl mx-auto space-y-5">

        {{-- ============================================ --}}
        {{-- PROFILE TAB --}}
        {{-- ============================================ --}}
        <div id="panelProfile" class="{{ $tab !== 'profile' ? 'hidden' : '' }} space-y-5">

            {{-- Identity header --}}
            <div class="bg-white rounded-lg border border-[#D9D9D9] p-5 flex items-center gap-4">
                <div class="w-14 h-14 rounded-full bg-[#002D5E] text-white flex items-center justify-center text-xl font-semibold shrink-0">
                    {{ collect(explode(' ', auth()->user()->name))->map(fn($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}
                </div>
                <div class="min-w-0">
                    <div class="text-base font-semibold text-[#333333] truncate">{{ auth()->user()->name }}</div>
                    <div class="text-sm text-[#6B7280] truncate">{{ auth()->user()->email }}</div>
                    <span class="inline-block mt-1 text-xs px-2 py-0.5 rounded-full bg-[#002D5E]/10 text-[#002D5E] capitalize">{{ auth()->user()->role }}</span>
                </div>
            </div>

            {{-- Your Activity --}}
            <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
                <h2 class="text-base font-medium text-[#333333] mb-1">Your Activity</h2>
                <p class="text-xs text-[#6B7280] mb-4">Records logged with this account since day 1.</p>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                    <div class="bg-white border border-[#D9D9D9] rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-[#002D5E] leading-none tracking-[-0.5px]">{{ number_format($activity->egg_logs) }}</div>
                        <div class="text-xs text-[#6B7280] mt-1">Egg log entries</div>
                    </div>
                    <div class="bg-white border border-[#D9D9D9] rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-[#002D5E] leading-none tracking-[-0.5px]">{{ number_format($activity->eggs_total) }}</div>
                        <div class="text-xs text-[#6B7280] mt-1">Eggs recorded</div>
                    </div>
                    <div class="bg-white border border-[#D9D9D9] rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-[#002D5E] leading-none tracking-[-0.5px]">{{ number_format($activity->feed_logs) }}</div>
                        <div class="text-xs text-[#6B7280] mt-1">Feed entries</div>
                    </div>
                    <div class="bg-white border border-[#D9D9D9] rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-[#002D5E] leading-none tracking-[-0.5px]">{{ number_format($activity->mortality_logs) }}</div>
                        <div class="text-xs text-[#6B7280] mt-1">Mortality records</div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
                <h2 class="text-base font-medium text-[#333333] mb-4">Your Profile</h2>

                <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm text-[#333333] mb-1.5">Name</label>
                        <input type="text" name="name" value="{{ old('name', auth()->user()->name) }}" required
                               class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-[#002D5E]">
                        @error('name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm text-[#333333] mb-1.5">Email</label>
                        <input type="email" name="email" value="{{ old('email', auth()->user()->email) }}" required
                               class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-[#002D5E]">
                        @error('email')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm text-[#333333] mb-1.5">Role</label>
                        <p class="text-sm text-[#333333] capitalize">{{ auth()->user()->role }}</p>
                        <p class="text-xs text-[#6B7280] mt-1">Role is managed by an administrator.</p>
                    </div>

                    <div>
                        <label class="block text-sm text-[#333333] mb-1.5">Member Since</label>
                        <p class="text-sm text-[#333333]">{{ auth()->user()->created_at->format('F d, Y') }}</p>
                    </div>

                    <button type="submit" class="bg-[#002D5E] text-white px-5 py-2.5 rounded-lg text-sm hover:bg-[#001F42]">Save Profile</button>
                </form>
            </div>

            {{-- Security Status --}}
            <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
                <h2 class="text-base font-medium text-[#333333] mb-4">Security Status</h2>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-[#333333]">Override PIN</span>
                        <span class="text-xs px-2 py-0.5 rounded-full {{ auth()->user()->override_pin_hash ? 'bg-[#D5E8D4] text-[#2D6A4F]' : 'bg-gray-200 text-gray-500' }}">
                            {{ auth()->user()->override_pin_hash ? 'Set' : 'Not set' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-[#333333]">Account Role</span>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-[#002D5E]/10 text-[#002D5E] capitalize">
                            {{ auth()->user()->role }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-[#333333]">Password</span>
                        <span class="text-xs text-[#6B7280]">Change anytime in Settings</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============================================ --}}
        {{-- ACCOUNT SETTINGS TAB --}}
        {{-- ============================================ --}}
        <div id="panelSettings" class="{{ $tab !== 'settings' ? 'hidden' : '' }} space-y-5">

            {{-- Change Password --}}
            <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
                <h2 class="text-base font-medium text-[#333333] mb-4">Change Password</h2>
                <form method="POST" action="{{ route('account.password') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm text-[#333333] mb-1.5">Current Password</label>
                        <div class="input-with-toggle relative">
                            <input type="password" name="current_password" required
                                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 pr-10 text-sm focus:outline-none focus:border-[#002D5E]">
                            <button type="button" onclick="toggleVisibility(this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-[#6B7280] hover:text-[#333333] transition-colors" aria-label="Show password">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                            </button>
                        </div>
                        @error('current_password')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm text-[#333333] mb-1.5">New Password</label>
                        <div class="input-with-toggle relative">
                            <input type="password" name="password" required
                                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 pr-10 text-sm focus:outline-none focus:border-[#002D5E]">
                            <button type="button" onclick="toggleVisibility(this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-[#6B7280] hover:text-[#333333] transition-colors" aria-label="Show password">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                            </button>
                        </div>
                        <p class="text-xs text-[#6B7280] mt-1">Minimum 8 characters.</p>
                        @error('password')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm text-[#333333] mb-1.5">Confirm New Password</label>
                        <div class="input-with-toggle relative">
                            <input type="password" name="password_confirmation" required
                                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 pr-10 text-sm focus:outline-none focus:border-[#002D5E]">
                            <button type="button" onclick="toggleVisibility(this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-[#6B7280] hover:text-[#333333] transition-colors" aria-label="Show password">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="bg-[#002D5E] text-white px-5 py-2.5 rounded-lg text-sm hover:bg-[#001F42]">Update Password</button>
                </form>
            </div>

            {{-- Override PIN --}}
            <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
                <h2 class="text-base font-medium text-[#333333] mb-1">{{ auth()->user()->override_pin_hash ? 'Change Override PIN' : 'Set Override PIN' }}</h2>
                <p class="text-xs text-[#6B7280] mb-4">Used to manually override a sensor-locked egg count in Egg Logging.</p>
                <form method="POST" action="{{ route('account.pin') }}" class="space-y-4">
                    @csrf
                    @if(auth()->user()->override_pin_hash)
                    <div>
                        <label class="block text-sm text-[#333333] mb-1.5">Current PIN</label>
                        <div class="input-with-toggle relative">
                            <input type="password" name="current_pin" inputmode="numeric" maxlength="6"
                                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 pr-10 text-sm focus:outline-none focus:border-[#002D5E]"
                                   placeholder="Leave blank to verify with password instead">
                            <button type="button" onclick="toggleVisibility(this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-[#6B7280] hover:text-[#333333] transition-colors" aria-label="Show PIN">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm text-[#333333] mb-1.5">Or Current Password</label>
                        <div class="input-with-toggle relative">
                            <input type="password" name="current_password"
                                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 pr-10 text-sm focus:outline-none focus:border-[#002D5E]">
                            <button type="button" onclick="toggleVisibility(this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-[#6B7280] hover:text-[#333333] transition-colors" aria-label="Show password">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                            </button>
                        </div>
                        @error('current_pin')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                    @endif
                    <div>
                        <label class="block text-sm text-[#333333] mb-1.5">New PIN (4-6 digits)</label>
                        <div class="input-with-toggle relative">
                            <input type="password" name="pin" inputmode="numeric" maxlength="6" required
                                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 pr-10 text-sm focus:outline-none focus:border-[#002D5E]">
                            <button type="button" onclick="toggleVisibility(this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-[#6B7280] hover:text-[#333333] transition-colors" aria-label="Show PIN">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                            </button>
                        </div>
                        @error('pin')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm text-[#333333] mb-1.5">Confirm New PIN</label>
                        <div class="input-with-toggle relative">
                            <input type="password" name="pin_confirmation" inputmode="numeric" maxlength="6" required
                                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 pr-10 text-sm focus:outline-none focus:border-[#002D5E]">
                            <button type="button" onclick="toggleVisibility(this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-[#6B7280] hover:text-[#333333] transition-colors" aria-label="Show PIN">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="bg-[#002D5E] text-white px-5 py-2.5 rounded-lg text-sm hover:bg-[#001F42]">Save PIN</button>
                </form>
            </div>

            {{-- Admin: Team management --}}
            @if($team)
            <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
                <div class="flex items-center justify-between mb-1">
                    <h2 class="text-base font-medium text-[#333333]">Team</h2>
                    <button type="button" onclick="openAddUserModal()"
                            class="flex items-center gap-1.5 bg-[#002D5E] text-white px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-[#001F42] transition-colors">
                        <i data-lucide="user-plus" class="w-3.5 h-3.5"></i> Add User
                    </button>
                </div>
                <p class="text-xs text-[#6B7280] mb-4">Manage who has access to LayRate.</p>
                <div class="space-y-2">
                    @foreach($team as $member)
                    <div class="flex items-center justify-between border border-[#D9D9D9] rounded-lg px-4 py-2.5 {{ !$member->is_active ? 'opacity-50' : '' }}">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-[#333333] truncate">{{ $member->name }}</span>
                                @if($member->id === auth()->id())
                                <span class="text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-500 shrink-0">You</span>
                                @endif
                                @if(!$member->is_active)
                                <span class="text-xs px-1.5 py-0.5 rounded bg-gray-200 text-gray-600 shrink-0">Deactivated</span>
                                @endif
                            </div>
                            <div class="text-xs text-[#6B7280] truncate">{{ $member->email }} · <span class="capitalize">{{ $member->role }}</span></div>
                        </div>
                        <div class="flex items-center gap-1 shrink-0">
                            <button type="button" onclick='openEditUserModal(@json($member))' class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Edit {{ $member->name }}">
                                <i data-lucide="pencil" class="w-4 h-4 text-[#6B7280]"></i>
                            </button>
                            @if($member->id !== auth()->id())
                            <form method="POST" action="{{ route('settings.users.toggle-active', $member->id) }}"
                                  data-confirm="{{ $member->is_active ? 'Deactivate '.$member->name.'? They will be signed out and unable to log in until reactivated.' : 'Reactivate '.$member->name.'?' }}"
                                  data-confirm-action="{{ $member->is_active ? 'Deactivate' : 'Reactivate' }}">
                                @csrf
                                <button type="submit" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="{{ $member->is_active ? 'Deactivate' : 'Reactivate' }} {{ $member->name }}">
                                    <i data-lucide="{{ $member->is_active ? 'user-x' : 'user-check' }}" class="w-4 h-4 text-[#6B7280]"></i>
                                </button>
                            </form>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Admin: staff PIN status --}}
            @if($staff)
            <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
                <h2 class="text-base font-medium text-[#333333] mb-4">Staff Override PIN Status</h2>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-[#D9D9D9]">
                            <th class="text-left text-xs text-[#6B7280] py-2 font-medium">Name</th>
                            <th class="text-left text-xs text-[#6B7280] py-2 font-medium">Role</th>
                            <th class="text-left text-xs text-[#6B7280] py-2 font-medium">PIN Set?</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($staff as $member)
                        <tr class="border-b border-[#D9D9D9]">
                            <td class="py-2">{{ $member->name }}</td>
                            <td class="py-2 capitalize">{{ $member->role }}</td>
                            <td class="py-2">
                                <span class="text-xs px-2 py-0.5 rounded-full {{ $member->pin_set ? 'bg-[#D5E8D4] text-[#2D6A4F]' : 'bg-gray-200 text-gray-500' }}">
                                    {{ $member->pin_set ? 'Set' : 'Not set' }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            {{-- Active Sessions --}}
            <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
                <h2 class="text-base font-medium text-[#333333] mb-1">Active Sessions</h2>
                <p class="text-xs text-[#6B7280] mb-4">Devices currently signed in to your account. Use the Danger Zone below to sign out every device except this one.</p>
                <div class="space-y-2">
                    @forelse($sessions as $s)
                    <div class="flex items-center justify-between border border-[#D9D9D9] rounded-lg px-4 py-2.5 {{ $s->current ? 'bg-[#002D5E]/5' : '' }}">
                        <div class="flex items-center gap-3 min-w-0">
                            <i data-lucide="{{ str_contains($s->device, 'Android') || str_contains($s->device, 'iOS') ? 'smartphone' : 'monitor' }}" class="w-4 h-4 text-[#6B7280] shrink-0"></i>
                            <div class="min-w-0">
                                <div class="text-sm text-[#333333] truncate">{{ $s->device }}</div>
                                <div class="text-xs text-[#6B7280]">{{ $s->ip }} · last active {{ $s->last_active->diffForHumans() }}</div>
                            </div>
                        </div>
                        @if($s->current)
                        <span class="text-xs px-2 py-0.5 rounded-full bg-[#D5E8D4] text-[#2D6A4F] shrink-0">This device</span>
                        @endif
                    </div>
                    @empty
                    <p class="text-sm text-[#6B7280]">No session data available.</p>
                    @endforelse
                </div>
            </div>

            {{-- Admin: farm configuration overview --}}
            @if($farmSettings)
            <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
                <h2 class="text-base font-medium text-[#333333] mb-1">Farm Configuration</h2>
                <p class="text-xs text-[#6B7280] mb-4">Current farm-level settings. Each group is edited on its own page.</p>
                <div class="space-y-4">
                    @foreach($farmSettings as $group => $cfg)
                    <div class="border border-[#D9D9D9] rounded-lg p-4">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-sm font-medium text-[#333333]">{{ $group }}</h3>
                            <a href="{{ $cfg['route'] }}" class="text-xs text-[#0075DE] hover:underline">Edit →</a>
                        </div>
                        <dl class="space-y-1">
                            @foreach($cfg['values'] as $label => $value)
                            <div class="flex items-center justify-between text-sm">
                                <dt class="text-[#6B7280]">{{ $label }}</dt>
                                <dd class="text-[#333333]">{{ $value }}</dd>
                            </div>
                            @endforeach
                        </dl>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Danger Zone --}}
            <div class="bg-white rounded-lg border border-red-200 p-5">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                        <i data-lucide="alert-triangle" class="w-5 h-5 text-red-600"></i>
                    </div>
                    <div>
                        <h2 class="text-base font-medium text-[#9b1c24]">Danger Zone</h2>
                        <p class="text-sm text-[#6B7280]">Sensitive actions that affect your account security.</p>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <h3 class="text-sm font-medium text-[#9b1c24] mb-1">Sign out of all other devices</h3>
                        <p class="text-xs text-[#6B7280] mb-3">This will invalidate all other active sessions for your account. Your current session will remain active.</p>
                        <form method="POST" action="{{ route('profile.logout-other-devices') }}" class="space-y-3">
                            @csrf
                            <div class="input-with-toggle relative">
                                <input type="password" name="logout_password" placeholder="Enter current password to confirm" required
                                       class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 pr-10 text-sm focus:outline-none focus:border-[#002D5E]">
                                <button type="button" onclick="toggleVisibility(this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-[#6B7280] hover:text-[#333333] transition-colors" aria-label="Show password">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                </button>
                            </div>
                            @error('logout_password')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                            <button type="submit" class="w-full sm:w-auto border border-red-300 text-red-700 bg-white hover:bg-red-50 px-5 py-2.5 rounded-lg text-sm font-medium transition-colors">
                                Sign out of all other devices
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

@if($team)
{{-- Add User Modal --}}
<div id="addUserModal" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeAddUserModal()"></div>
    <div class="relative w-full max-w-md rounded-2xl p-6" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Add User</h2>
            <button type="button" onclick="closeAddUserModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
            </button>
        </div>
        <form method="POST" action="{{ route('settings.users.store') }}"
              data-loading="Creating account..." data-loading-title="Adding User">
            @csrf
            <label class="block text-sm text-[#333333] mb-1.5">Name</label>
            <input name="name" required class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">

            <label class="block text-sm text-[#333333] mb-1.5">Email</label>
            <input name="email" type="email" required class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">

            <label class="block text-sm text-[#333333] mb-1.5">Temporary Password</label>
            <input name="password" type="password" required minlength="8" class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-1 focus:outline-none focus:border-[#002D5E]">
            <p class="text-xs text-[#6B7280] mb-4">Minimum 8 characters. Share this with the new user — they can change it in their own Settings.</p>

            <label class="block text-sm text-[#333333] mb-1.5">Role</label>
            <select name="role" required class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-5 bg-white focus:outline-none focus:border-[#002D5E]">
                <option value="operator">Operator</option>
                <option value="admin">Admin</option>
            </select>

            <div class="flex gap-3">
                <button type="button" onclick="closeAddUserModal()" class="flex-1 py-2.5 text-sm font-medium rounded-lg transition-colors" style="color: #1f1f1f; border: 1px solid #e6e6e6;">Cancel</button>
                <x-button type="submit" class="flex-1 py-2.5">Add User</x-button>
            </div>
        </form>
    </div>
</div>

{{-- Edit User Modal --}}
<div id="editUserModal" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeEditUserModal()"></div>
    <div class="relative w-full max-w-md rounded-2xl p-6" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Edit User</h2>
            <button type="button" onclick="closeEditUserModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
            </button>
        </div>
        <form id="editUserForm" method="POST" data-loading="Saving changes..." data-loading-title="Saving">
            @csrf @method('PUT')
            <label class="block text-sm text-[#333333] mb-1.5">Name</label>
            <input id="editUserName" name="name" required class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">

            <label class="block text-sm text-[#333333] mb-1.5">Email</label>
            <input id="editUserEmail" name="email" type="email" required class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-[#002D5E]">

            <label class="block text-sm text-[#333333] mb-1.5">Role</label>
            <select id="editUserRole" name="role" required class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-5 bg-white focus:outline-none focus:border-[#002D5E]">
                <option value="operator">Operator</option>
                <option value="admin">Admin</option>
            </select>

            <div class="flex gap-3">
                <button type="button" onclick="closeEditUserModal()" class="flex-1 py-2.5 text-sm font-medium rounded-lg transition-colors" style="color: #1f1f1f; border: 1px solid #e6e6e6;">Cancel</button>
                <x-button type="submit" class="flex-1 py-2.5">Save Changes</x-button>
            </div>
        </form>
    </div>
</div>

<script>
    function openAddUserModal() {
        const m = document.getElementById('addUserModal');
        m.classList.remove('hidden'); m.classList.add('flex');
    }
    function closeAddUserModal() {
        const m = document.getElementById('addUserModal');
        m.classList.add('hidden'); m.classList.remove('flex');
    }
    function openEditUserModal(user) {
        const form = document.getElementById('editUserForm');
        form.action = '{{ url('settings/users') }}/' + user.id;
        document.getElementById('editUserName').value = user.name;
        document.getElementById('editUserEmail').value = user.email;
        document.getElementById('editUserRole').value = user.role;
        const m = document.getElementById('editUserModal');
        m.classList.remove('hidden'); m.classList.add('flex');
    }
    function closeEditUserModal() {
        const m = document.getElementById('editUserModal');
        m.classList.add('hidden'); m.classList.remove('flex');
    }
</script>
@endif

<script>
    // Hide the layout's flash-message banners on this page — we use toasts instead.
    (function() {
        document.querySelectorAll('div[class*="bg-green-50"], div[class*="bg-red-50"]').forEach(function(el) {
            el.remove();
        });
    })();

    @if(session('success'))
    document.addEventListener('turbo:load', function showProfileSuccess() {
        showNotification('{{ session('success') }}', 'success');
        document.removeEventListener('turbo:load', showProfileSuccess);
    });
    @endif

    @if(session('error'))
    document.addEventListener('turbo:load', function showProfileError() {
        showNotification('{{ session('error') }}', 'error');
        document.removeEventListener('turbo:load', showProfileError);
    });
    @endif

    function switchProfileTab(tab) {
        document.getElementById('panelProfile').classList.toggle('hidden', tab !== 'profile');
        document.getElementById('panelSettings').classList.toggle('hidden', tab !== 'settings');

        const nav = document.getElementById('profile-tabs-nav');
        if (nav) {
            nav.querySelectorAll('button').forEach(btn => {
                btn.classList.remove('border-[#002D5E]', 'text-[#002D5E]');
                btn.classList.add('border-transparent', 'text-[#6B7280]');
            });
            const active = nav.querySelector('button[onclick*="\'' + tab + '\'" ]');
            if (active) {
                active.classList.remove('border-transparent', 'text-[#6B7280]');
                active.classList.add('border-[#002D5E]', 'text-[#002D5E]');
            }
        }

        // Update URL query param without reload so direct links/bookmarks land on the right tab
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        window.history.replaceState({}, '', url);
    }

    function toggleVisibility(btn) {
        const wrapper = btn.closest('.input-with-toggle');
        if (!wrapper) return;
        const input = wrapper.querySelector('input');
        const icon = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.setAttribute('data-lucide', 'eye-off');
            btn.setAttribute('aria-label', btn.getAttribute('aria-label').replace('Show', 'Hide'));
        } else {
            input.type = 'password';
            icon.setAttribute('data-lucide', 'eye');
            btn.setAttribute('aria-label', btn.getAttribute('aria-label').replace('Hide', 'Show'));
        }
        if (window.lucide) lucide.createIcons();
    }
</script>
@endsection
