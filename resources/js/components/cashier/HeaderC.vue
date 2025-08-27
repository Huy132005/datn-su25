<script setup>
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { router as inertiaRouter, Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Clock, Lock, LogOut, Monitor, Moon, RefreshCw, Sun, User, Wifi, WifiOff } from 'lucide-vue-next';

const isDropdownOpen = ref(false);
const isLocked = ref(false);
const isRefreshing = ref(false);
const currentTime = ref('');
const isOnline = ref(navigator.onLine);
const dropdownRef = ref(null);

let intervalId = null;
let idleTimeout = null;

const page = usePage();
const cashierName = computed(() => page.props.auth?.user?.name || 'Ẩn danh');

const getShiftNameByTime = () => {
    const hour = new Date().getHours();
    if (hour >= 6 && hour < 12) return 'Sáng';
    if (hour >= 12 && hour < 18) return 'Chiều';
    if (hour >= 18 && hour < 22) return 'Tối';
    return 'Đêm';
};
const shiftName = computed(() => getShiftNameByTime());

const cashierInitial = computed(() => {
    const name = cashierName.value || '';
    return name
        .trim()
        .split(' ')
        .map((w) => w[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();
});

const updateTime = () => {
    const now = new Date();
    const h = now.getHours().toString().padStart(2, '0');
    const m = now.getMinutes().toString().padStart(2, '0');
    const s = now.getSeconds().toString().padStart(2, '0');
    const day = now.toLocaleDateString('vi-VN', { weekday: 'short' });
    const date = now.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
    currentTime.value = `${h}:${m}:${s} | ${day}, ${date}`;
};

const refreshOrder = () => {
    isRefreshing.value = true;
    inertiaRouter.reload({
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            isRefreshing.value = false;
        },
    });
};

const lockScreen = () => (isLocked.value = true);
const unlockScreen = () => (isLocked.value = false);

const handleLogout = async () => {
    isDropdownOpen.value = false;
    const confirmCloseShift = window.confirm('Bạn có muốn đóng ca làm việc trước khi đăng xuất không?');
    
    inertiaRouter.post(
        '/cashier/logout',
        { closeShift: confirmCloseShift },
        {
            onFinish: () => {
                localStorage.removeItem('userToken');
            },
        },
    );
};

const toggleDropdown = () => (isDropdownOpen.value = !isDropdownOpen.value);

const handleClickOutside = (e) => {
    if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
        isDropdownOpen.value = false;
    }
};

const handleGlobalKeydown = (e) => {
    if (e.key === 'Escape' && isDropdownOpen.value) {
        toggleDropdown();
    }
};

const resetIdleTimer = () => {
    clearTimeout(idleTimeout);
    idleTimeout = setTimeout(
        () => {
            isLocked.value = true;
        },
        5 * 60 * 1000,
    );
};

window.addEventListener('online', () => (isOnline.value = true));
window.addEventListener('offline', () => (isOnline.value = false));

const isDark = ref(localStorage.getItem('theme') === 'dark');

const applyTheme = () => {
    if (isDark.value) {
        document.documentElement.classList.add('dark');
        localStorage.setItem('theme', 'dark');
    } else {
        document.documentElement.classList.remove('dark');
        localStorage.setItem('theme', 'light');
    }
};

const toggleDarkMode = () => {
    isDark.value = !isDark.value;
    applyTheme();
};

onMounted(() => {
    updateTime();
    intervalId = setInterval(updateTime, 1000);

    window.addEventListener('click', handleClickOutside);
    window.addEventListener('keydown', handleGlobalKeydown);

    ['mousemove', 'keydown', 'mousedown', 'touchstart'].forEach((event) => {
        window.addEventListener(event, resetIdleTimer);
    });
    resetIdleTimer();
    applyTheme();
});

onUnmounted(() => {
    clearInterval(intervalId);
    window.removeEventListener('click', handleClickOutside);
    window.removeEventListener('keydown', handleGlobalKeydown);

    ['mousemove', 'keydown', 'mousedown', 'touchstart'].forEach((event) => {
        window.removeEventListener(event, resetIdleTimer);
    });
    clearTimeout(idleTimeout);
});
</script>

<template>
    <header class="flex items-center justify-between border-b border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-center space-x-4">
            <Link :href="route('cashier.dashboard')" title="Trang chính">
                <div class="text-xl font-semibold tracking-wide text-blue-800">
                    <AppLogoIcon imageUrl="/storage/piclumen-1747750187180.png" className="w-12 h-12" />
                </div>
            </Link>
            <div class="flex items-center space-x-2 text-xs">
                <div class="flex items-center gap-1 rounded bg-gray-100 px-2 py-1" title="Tên thu ngân">
                    <User class="h-4 w-4" /> {{ cashierName }}
                </div>
                <div class="flex items-center gap-1 rounded bg-gray-100 px-2 py-1" title="Ca làm"><Clock class="h-4 w-4" /> Ca: {{ shiftName }}</div>
                <div class="flex items-center gap-1 rounded bg-gray-100 px-2 py-1" title="Mã máy POS"><Monitor class="h-4 w-4" /> Máy: POS01</div>
                <div
                    :class="[
                        'flex items-center gap-1 rounded px-2 py-1 font-semibold',
                        isOnline ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700',
                    ]"
                    title="Trạng thái kết nối"
                >
                    <component :is="isOnline ? Wifi : WifiOff" class="h-4 w-4" />
                    {{ isOnline ? 'Online' : 'Offline' }}
                </div>
            </div>
        </div>
        <div class="flex items-center space-x-4">
            <span class="rounded bg-gray-200 px-3 py-1 font-mono tracking-wider">{{ currentTime }}</span>
            <button @click="refreshOrder" class="flex items-center gap-1 text-sm text-blue-600 hover:underline" title="Làm mới đơn hàng">
                <RefreshCw v-if="isRefreshing" class="h-4 w-4 animate-spin" />
                <RefreshCw v-else class="h-4 w-4" />
                <span v-if="!isRefreshing">Làm mới</span>
            </button>
            <button @click="lockScreen" class="flex items-center gap-1 text-sm text-red-600 hover:underline" title="Khóa màn hình">
                <Lock class="h-4 w-4" /> Khóa
            </button>
            <button @click="toggleDarkMode" class="text-xl text-yellow-500 hover:text-yellow-600" title="Đổi chế độ sáng/tối">
                <Sun v-if="!isDark" class="h-5 w-5" />
                <Moon v-else class="h-5 w-5" />
            </button>
            <div class="relative" ref="dropdownRef">
                <button
                    @click="toggleDropdown"
                    class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-sm font-bold text-white uppercase"
                    aria-label="Mở menu người dùng"
                >
                    {{ cashierInitial }}
                </button>
                <div v-if="isDropdownOpen" class="absolute right-0 z-50 mt-1 w-44 rounded border bg-white shadow">
                    <div class="border-b px-4 py-2 text-gray-600">{{ cashierName }}</div>
                    <button @click="handleLogout" class="flex w-full items-center gap-1 px-4 py-2 text-left text-gray-700 hover:bg-gray-100">
                        <LogOut class="h-4 w-4" /> Đăng xuất
                    </button>
                </div>
            </div>
        </div>
    </header>
    <div
        v-if="isLocked"
        @click="unlockScreen"
        class="bg-opacity-70 fixed inset-0 z-50 flex cursor-pointer items-center justify-center bg-black text-xl font-semibold text-white select-none"
    >
        <Lock class="mr-2 h-6 w-6" /> Đã khóa màn hình — Bấm để mở khóa
    </div>
</template>