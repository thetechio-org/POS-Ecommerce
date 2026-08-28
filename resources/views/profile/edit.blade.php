<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profile Settings</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-800 dark:text-gray-200 font-sans antialiased">

    <!-- Header -->
    <header class="bg-white dark:bg-gray-800 shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Profile Settings</h1>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                Manage your personal info, password, and account preferences.
            </p>
        </div>
    </header>

    <!-- Main Content -->
    <main class="py-12">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">

            @if(session('success'))
                <div 
                    x-data="{ show: true }" 
                    x-init="setTimeout(() => show = false, 3000)" 
                    x-show="show"
                >
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6" role="alert">
                        <strong class="font-bold">Success!</strong>
                        <span class="block sm:inline">{{ session('success') }}</span>
                    </div>
                </div>
            @endif


            <!-- Profile Information -->
            <section class="bg-white dark:bg-gray-800 rounded-xl shadow p-8">
                <h2 class="text-2xl font-semibold text-gray-800 dark:text-white mb-6">Update Profile</h2>
                <form method="POST" action="{{ route('profile.update') }}">
                    @csrf
                    @method('patch')
                    <div class="grid gap-6">
                        <div>
                            <label for="name" class="block text-sm font-medium mb-1">Full Name</label>
                            <input id="name" name="name" type="text" value="{{ old('name') ?? auth()->user()->name }}" class="w-full px-4 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-medium mb-1">Email Address</label>
                            <input id="email" name="email" type="email" value="{{ old('email') ?? auth()->user()->email }}" class="w-full px-4 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-5 rounded-md transition duration-150">
                                Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </section>

            <!-- Change Password -->
            <section class="bg-white dark:bg-gray-800 rounded-xl shadow p-8">
                <h2 class="text-2xl font-semibold text-gray-800 dark:text-white mb-6">Change Password</h2>
                <form method="POST" action="{{ route('password.update') }}">
                    @csrf
                    @method('put')
                    <div class="grid gap-6">
                        <div>
                            <label for="current_password" class="block text-sm font-medium mb-1">Current Password</label>
                            <input id="current_password" name="current_password" type="password" class="w-full px-4 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 focus:ring-2 focus:ring-green-500 focus:outline-none">
                        </div>
                        <div>
                            <label for="password" class="block text-sm font-medium mb-1">New Password</label>
                            <input id="password" name="password" type="password" class="w-full px-4 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 focus:ring-2 focus:ring-green-500 focus:outline-none">
                            @error('password')
                                <p class="text-sm text-red-500 mt-1">{{ $message }}</p>
                            @enderror

                        </div>
                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium mb-1">Confirm New Password</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" class="w-full px-4 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 focus:ring-2 focus:ring-green-500 focus:outline-none">
                        </div>

                        <div>
                            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-5 rounded-md transition duration-150">
                                Update Password
                            </button>
                        </div>
                    </div>
                </form>
            </section>

            <!-- Delete Account (Admin only) -->
            @if(auth()->user()->role?->name === 'Admin')
            <section class="bg-white dark:bg-gray-800 rounded-xl shadow p-8 border border-red-200 dark:border-red-600">
                <h2 class="text-2xl font-semibold text-red-600 dark:text-red-400 mb-4">Delete Account</h2>
                <p class="text-sm text-gray-600 dark:text-gray-300 mb-6">
                    This action is permanent. All your data will be deleted and cannot be recovered.
                </p>
                <form method="POST" action="{{ route('profile.destroy') }}">
                    @csrf
                    @method('delete')
                
                    <div class="mb-4">
                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Confirm Password</label>
                        <input id="password" name="password" type="password" required class="w-full px-4 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 focus:ring-2 focus:ring-red-500 focus:outline-none">
                        @error('userDeletion.password')
                            <p class="text-sm text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-5 rounded-md transition duration-150">
                        Delete Account
                    </button>
                </form>

            </section>
            @endif

        </div>
    </main>
</body>
</html>
