@extends('layouts.app')
@section('title', 'Account Settings')

@section('content')
<main class="p-5 space-y-5 max-w-2xl">

    <h1 class="text-xl font-medium text-[#333333]">Account Settings</h1>

    {{-- Change Password --}}
    <div class="bg-white rounded-lg border border-[#D9D9D9] p-6">
        <h2 class="text-base font-medium text-[#333333] mb-4">Change Password</h2>
        <form method="POST" action="{{ route('account.password') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm text-[#333333] mb-1.5">Current Password</label>
                <input type="password" name="current_password" required
                       class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-[#002D5E]">
                @error('current_password')<p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm text-[#333333] mb-1.5">New Password</label>
                <input type="password" name="password" required
                       class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-[#002D5E]">
                @error('password')<p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm text-[#333333] mb-1.5">Confirm New Password</label>
                <input type="password" name="password_confirmation" required
                       class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-[#002D5E]">
            </div>
            <button type="submit" class="bg-[#002D5E] text-white px-5 py-2.5 rounded-lg text-sm hover:bg-[#001F42]">Update Password</button>
        </form>
    </div>

    {{-- Override PIN --}}
    <div class="bg-white rounded-lg border border-[#D9D9D9] p-6">
        <h2 class="text-base font-medium text-[#333333] mb-1">{{ auth()->user()->override_pin_hash ? 'Change Override PIN' : 'Set Override PIN' }}</h2>
        <p class="text-xs text-[#6B7280] mb-4">Used to manually override a sensor-locked egg count in Egg Logging.</p>
        <form method="POST" action="{{ route('account.pin') }}" class="space-y-4">
            @csrf
            @if(auth()->user()->override_pin_hash)
            <div>
                <label class="block text-sm text-[#333333] mb-1.5">Current PIN</label>
                <input type="text" name="current_pin" inputmode="numeric" maxlength="6"
                       class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-[#002D5E]"
                       placeholder="Leave blank to verify with password instead">
            </div>
            <div>
                <label class="block text-sm text-[#333333] mb-1.5">Or Current Password</label>
                <input type="password" name="current_password"
                       class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-[#002D5E]">
                @error('current_pin')<p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>
            @endif
            <div>
                <label class="block text-sm text-[#333333] mb-1.5">New PIN (4-6 digits)</label>
                <input type="text" name="pin" inputmode="numeric" maxlength="6" required
                       class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-[#002D5E]">
                @error('pin')<p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm text-[#333333] mb-1.5">Confirm New PIN</label>
                <input type="text" name="pin_confirmation" inputmode="numeric" maxlength="6" required
                       class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-[#002D5E]">
            </div>
            <button type="submit" class="bg-[#002D5E] text-white px-5 py-2.5 rounded-lg text-sm hover:bg-[#001F42]">Save PIN</button>
        </form>
    </div>

    {{-- Admin: staff PIN status --}}
    @if($staff)
    <div class="bg-white rounded-lg border border-[#D9D9D9] p-6">
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

</main>
@endsection
