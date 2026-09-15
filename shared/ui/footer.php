<?php
?>
    </main>
    <button id="backToTopBtn" onclick="window.scrollTo({top: 0, behavior: 'smooth'});" 
            class="fixed bottom-8 right-8 w-14 h-14 bg-white/90 dark:bg-[#0f172a]/90 backdrop-blur-xl text-indigo-600 dark:text-indigo-400 rounded-2xl shadow-xl flex items-center justify-center transition-all duration-500 opacity-0 translate-y-10 pointer-events-none hover:bg-indigo-600 hover:text-white dark:hover:bg-indigo-500 z-50 border border-slate-200 dark:border-slate-800">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"></path>
        </svg>
    </button>

    <script>
        window.addEventListener('scroll', function() {
            const btn = document.getElementById('backToTopBtn');
            if (window.scrollY > 200) {
                btn.classList.remove('opacity-0', 'translate-y-10', 'pointer-events-none');
                btn.classList.add('opacity-100', 'translate-y-0', 'pointer-events-auto');
            } else {
                btn.classList.add('opacity-0', 'translate-y-10', 'pointer-events-none');
                btn.classList.remove('opacity-100', 'translate-y-0', 'pointer-events-auto');
            }
        });
    </script>
</body>
</html>