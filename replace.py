import re

with open('resources/views/welcome.blade.php', 'r') as f:
    content = f.read()

new_main = """            <main class="w-full lg:max-w-4xl grid grid-cols-1 md:grid-cols-2 gap-6">
                <a href="{{ route('login', ['type' => 'operations']) }}" class="group flex flex-col justify-center items-center p-10 bg-white dark:bg-[#161615] rounded-xl shadow-[0_4px_6px_-1px_rgba(0,0,0,0.1),0_2px_4px_-1px_rgba(0,0,0,0.06)] hover:shadow-[0_10px_15px_-3px_rgba(0,0,0,0.1),0_4px_6px_-2px_rgba(0,0,0,0.05)] border border-[#e3e3e0] dark:border-[#3E3E3A] hover:border-[#19140035] dark:hover:border-[#62605b] transition-all duration-300">
                    <svg class="w-16 h-16 mb-6 text-[#1b1b18] dark:text-[#EDEDEC] group-hover:text-[#F53003] dark:group-hover:text-[#FF4433] transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    <h2 class="text-3xl font-semibold mb-3 text-[#1b1b18] dark:text-[#EDEDEC] group-hover:text-[#F53003] dark:group-hover:text-[#F61500] transition-colors">Operations</h2>
                    <p class="text-center text-[#706f6c] dark:text-[#A1A09A]">Log in to operations portal to manage day-to-day activities and reports.</p>
                </a>
                
                <a href="{{ route('login', ['type' => 'accounting']) }}" class="group flex flex-col justify-center items-center p-10 bg-white dark:bg-[#161615] rounded-xl shadow-[0_4px_6px_-1px_rgba(0,0,0,0.1),0_2px_4px_-1px_rgba(0,0,0,0.06)] hover:shadow-[0_10px_15px_-3px_rgba(0,0,0,0.1),0_4px_6px_-2px_rgba(0,0,0,0.05)] border border-[#e3e3e0] dark:border-[#3E3E3A] hover:border-[#19140035] dark:hover:border-[#62605b] transition-all duration-300">
                    <svg class="w-16 h-16 mb-6 text-[#1b1b18] dark:text-[#EDEDEC] group-hover:text-[#F53003] dark:group-hover:text-[#FF4433] transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <h2 class="text-3xl font-semibold mb-3 text-[#1b1b18] dark:text-[#EDEDEC] group-hover:text-[#F53003] dark:group-hover:text-[#F61500] transition-colors">Accounting</h2>
                    <p class="text-center text-[#706f6c] dark:text-[#A1A09A]">Log in to accounting portal to manage finances and financial statements.</p>
                </a>
            </main>"""

# Replace everything from <main ... to </main>
import re
new_content = re.sub(r'<main.*?</main>', new_main, content, flags=re.DOTALL)

with open('resources/views/welcome.blade.php', 'w') as f:
    f.write(new_content)

print("Replaced!")
