@extends('layouts.app')
@section('title', 'Profile')

@section('content')
<div class="space-y-5">

    <x-page-header title="Profile" subtitle="Manage your account and security settings" />

    {{-- Tabs --}}
    <div id="profile-tabs-nav">
        <x-underline-tabs :tabs="[
            'profile'  => ['label' => 'Profile',         'icon' => 'user',     'onclick' => 'switchProfileTab(\'profile\')'],
            'settings' => ['label' => 'Account Settings', 'icon' => 'settings', 'onclick' => 'switchProfileTab(\'settings\')'],
        ]" active="{{ $tab }}" />
    </div>

    <div class="max-w-2xl mx-auto space-y-5">

        {{-- ============================================ --}}
        {{-- PROFILE TAB --}}
        {{-- ============================================ --}}
        <div id="panelProfile" class="{{ $tab !== 'profile' ? 'hidden' : '' }} space-y-5">
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
                        <span class="text-xs text-[#6B7280]">Change anytime in Account Settings</span>
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
