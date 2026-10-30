<x-layouts.guest>
    <div class="bg-white rounded-2xl shadow-xl p-8 w-full max-w-md">
        <!-- Login Header -->
        <div class="text-center mb-8">
            <h2 class="text-2xl font-bold text-dark-slate">Welcome Back</h2>
            <p class="text-gray-600 mt-2">Sign in to your AgriConnect ERP account</p>
        </div>

        <!-- Login Form -->
        <form method="POST" action="#">
            @csrf
            
            <!-- Email Address -->
            <div class="mb-6">
                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                    Email Address
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                        </svg>
                    </div>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           required 
                           autofocus 
                           placeholder="Enter your email address"
                           class="pl-10 w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-green focus:border-transparent focus:outline-none transition duration-150 ease-in-out">
                </div>
            </div>

            <!-- Password -->
            <div class="mb-6">
                <div class="flex items-center justify-between mb-2">
                    <label for="password" class="block text-sm font-medium text-gray-700">
                        Password
                    </label>
                    <a href="#" class="text-sm text-emerald-green hover:text-emerald-green/80 font-medium">
                        Forgot password?
                    </a>
                </div>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           required 
                           placeholder="Enter your password"
                           class="pl-10 w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-green focus:border-transparent focus:outline-none transition duration-150 ease-in-out">
                </div>
            </div>

            <!-- Remember Me -->
            <div class="mb-6">
                <div class="flex items-center">
                    <input type="checkbox" 
                           id="remember" 
                           name="remember" 
                           class="h-4 w-4 text-emerald-green focus:ring-emerald-green border-gray-300 rounded">
                    <label for="remember" class="ml-2 block text-sm text-gray-700">
                        Remember me
                    </label>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit"
                    class="w-full bg-emerald-green text-white font-medium py-3 px-4 rounded-lg hover:bg-emerald-green/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-green transition duration-150 ease-in-out">
                Sign In
            </button>
        </form>

        <!-- Demo Credentials -->
        <div class="mt-8 p-4 bg-light-sage rounded-lg">
            <h4 class="text-sm font-medium text-dark-slate mb-2">Demo Credentials:</h4>
            <div class="space-y-1 text-sm text-gray-600">
                <p><span class="font-medium">Email:</span> admin@agriconnect.local</p>
                <p><span class="font-medium">Password:</span> password</p>
            </div>
        </div>

        <!-- Additional Info -->
        <div class="mt-8 text-center">
            <p class="text-sm text-gray-600">
                By signing in, you agree to our 
                <a href="#" class="text-emerald-green hover:text-emerald-green/80 font-medium">Terms of Service</a> 
                and 
                <a href="#" class="text-emerald-green hover:text-emerald-green/80 font-medium">Privacy Policy</a>
            </p>
        </div>
    </div>
</x-layouts.guest>