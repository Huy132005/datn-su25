<script setup lang="ts">
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { FilePlus2, KeyRound, Layers3, LayoutGrid, PackageSearch, RotateCw, Truck, User, UserCog, Users, Warehouse, PencilRuler, TrendingUp } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import SidebarDropdown from './SideBarDropdown.vue';

</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="flex-1 px-4">
            <!-- Tổng quan (dropdown) -->
            <SidebarDropdown
                :icon="LayoutGrid"
                label="Tổng quan"
                :items="[
                    { label: 'Tổng quan nhập hàng', href: '/dashboard', icon: LayoutGrid },
                    { label: 'Thống kê thử nghiệm', href: '/test', icon: TrendingUp },
                ]"
                class="mt-2"
            />

            <!-- Quản lý hàng hóa -->
            <SidebarDropdown
                :icon="PackageSearch"
                label="Quản lý hàng hóa"
                :items="[
                    { label: 'Quản lý sản phẩm', href: route('admin.products.index'), icon: LayoutGrid },
                    { label: 'Quản lý danh mục', href: route('admin.categories.index'), icon: PackageSearch },
                    { label: 'Quản lý nhà cung cấp', href: route('admin.suppliers.index'), icon: Warehouse },

                    { label: 'Quản lý đơn vị tính', href: route('admin.units.index'), icon: PencilRuler  },
                ]"
                class="mt-4"
            />

            <!-- Quản lý kho hàng -->
            <SidebarDropdown
                :icon="Warehouse"
                label="Quản lý kho hàng"
                :items="[
                    { label: 'Quản lý đơn đặt hàng', href: route('admin.purchase-orders.index'), icon: Truck },
                    { label: 'Quản lý phiếu nhập hàng', href: route('admin.batches.index'), icon: FilePlus2 },

                    { label: 'Quản lý phiếu trả hàng', href: route('admin.purchaseReturn.index'), icon: RotateCw },

                    // { label: 'Quản lý lô hàng', href: route('admin.product-batches.index'), icon: Boxes },
                    { label: 'Quản lý tồn kho', href: route('admin.inventory.index'), icon: Layers3 },
                ]"
                class="mt-2"
            />

            <SidebarDropdown
                :icon="Users"
                label="Quản lý người dùng"
                :items="[
                    { label: 'Quản lý khách hàng', href: route('admin.customers.index'), icon: User },
                    { label: 'Quản lý nhân viên', href: route('admin.users.index'), icon: UserCog },
                    { label: 'Quản lý tài khoản', href: '#', icon: KeyRound },
                ]"
                class="mt-2"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>

    <slot />
</template>
