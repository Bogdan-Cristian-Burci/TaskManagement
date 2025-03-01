<header class="bg-transparent  fixed top-0 w-full z-10">
    <div class="container mx-auto px-4 flex justify-between items-center py-4">
        <div class="text-2xl font-bold">Logo</div>
        <nav>
            <ul class="hidden md:flex space-x-6">
                <li><a href="#" class="hover:text-blue-500">Home</a></li>
                <li class="relative group">
                    <a href="#" class="hover:text-blue-500">Services</a>
                    <ul class="absolute left-0 mt-2 w-48 bg-white shadow-lg hidden group-hover:block">
                        <li><a href="#" class="block px-4 py-2 hover:bg-gray-200">Resume Builder</a></li>
                        <li><a href="#" class="block px-4 py-2 hover:bg-gray-200">Task Management</a></li>
                    </ul>
                </li>
                <li><a href="#" class="hover:text-blue-500">Contact</a></li>
                <li><a href="#" class="hover:text-blue-500">About Us</a></li>
            </ul>
            <button id="menu-btn" class="md:hidden text-xl">&#9776;</button>
        </nav>
    </div>
    <ul id="mobile-menu" class="hidden flex flex-col bg-white shadow-md md:hidden">
        <li><a href="#" class="block px-4 py-2">Home</a></li>
        <li class="relative">
            <button id="mobile-services-btn" class="block px-4 py-2 w-full text-left">Services</button>
            <ul class="hidden px-4" id="mobile-submenu">
                <li><a href="#" class="block py-2">Resume Builder</a></li>
                <li><a href="#" class="block py-2">Task Management</a></li>
            </ul>
        </li>
        <li><a href="#" class="block px-4 py-2">Contact</a></li>
        <li><a href="#" class="block px-4 py-2">About Us</a></li>
    </ul>
</header>
