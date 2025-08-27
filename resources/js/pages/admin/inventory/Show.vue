<script setup lang="ts">
import FlashMessage from '@/components/FlashMessage.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Chi tiết tồn kho',
        href: '/admin/inventory',
    },
];

// Reactive state cho chức năng điều chỉnh tồn kho
const editingBatchItems = ref<Record<number, boolean>>({});
const newQuantities = ref<Record<number, number>>({});
const adjustReasons = ref<Record<number, string>>({});
const isLoading = ref(false);

// Khai báo kiểu dữ liệu cho product
interface Product {
    id: number;
    name: string;
    image_url: string;
    sku: string | null;
    barcode: number;
    stock_quantity: number;
    selling_price: number;
    description: string | null;
    category?: { id: number; name: string };
    unit?: { id: number; name: string };
    suppliers?: { id: number; name: string; pivot?: { purchase_price?: number } }[];
}

interface Unit {
    id: number;
    name: string;
}

interface Category {
    id: number;
    name: string;
}

interface Supplier {
    id: number;
    name: string;
    email?: string;
    phone?: string;
    address?: string;
    pivot: {
        purchase_price: number;
    };
}

interface Batch {
    id: number;
    batch_number: string;
    purchase_order_id: number;
    supplier_id: number | null;
    supplier?: Supplier | null;
    received_date: string;
    invoice_number: string | null;
    total_amount: number;
    payment_status: 'unpaid' | 'partially_paid' | 'paid';
    paid_amount: number;
    receipt_status: 'partially_received' | 'completed' | 'cancelled';
    notes: string | null;
    created_by: number;
    discount_amount: number;
    discount_type: string;
    updated_by: number | null;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
    expected_delivery_date?: string;
}

interface BatchItem {
    id: number;
    batch_id: number;
    product_id: number;
    purchase_order_item_id: number;
    product_name: string;
    product_sku: string;
    ordered_quantity: number;
    received_quantity: number;
    rejected_quantity: number;
    remaining_quantity: number;
    current_quantity: number;
    purchase_price: number;
    total_amount: number;
    manufacturing_date: string | null;
    expiry_date: string | null;
    inventory_status: 'active' | 'low_stock' | 'out_of_stock' | 'expired' | 'damaged' | 'expiring_soon';
    product?: {
        name: string;
        sku: string;
        unit?: {
            name: string;
        };
        image_url?: string;
    };
}

type AvailableProduct = {
    product_id: number;
    name: string;
    value: number;
};

const page = usePage<SharedData>();
const availableProducts = page.props.availableProducts as AvailableProduct[];
// Nhận prop product từ Inertia
const props = defineProps<{
    product: Product;
    unit: Unit;
    category: Category;
    batch: Batch[];
    batchItems: BatchItem[];
}>();

function formatCurrency(value: number | null): string {
    if (value === null || isNaN(value)) return 'N/A';
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);
}

function getBatchNumber(batchId: number): string {
    const foundBatch = props.batch.find((b) => b.id === batchId);
    return foundBatch ? foundBatch.batch_number : `#${batchId}`;
}

function goBack() {
    router.visit('/admin/inventory');
}

// Các function cho chức năng điều chỉnh tồn kho batch items
function startEditingBatchItem(batchItemId: number, currentQuantity: number) {
    editingBatchItems.value[batchItemId] = true;
    newQuantities.value[batchItemId] = currentQuantity;
    adjustReasons.value[batchItemId] = '';
}

function cancelEditingBatchItem(batchItemId: number) {
    delete editingBatchItems.value[batchItemId];
    delete newQuantities.value[batchItemId];
    delete adjustReasons.value[batchItemId];
}

function updateQuantity(batchItemId: number, value: string) {
    newQuantities.value[batchItemId] = parseInt(value) || 0;
}

function updateReason(batchItemId: number, value: string) {
    adjustReasons.value[batchItemId] = value;
}

async function saveBatchItemAdjustment(batchItemId: number, oldQuantity: number) {
    const newQuantity = newQuantities.value[batchItemId];

    if (newQuantity === oldQuantity) {
        cancelEditingBatchItem(batchItemId);
        return;
    }

    isLoading.value = true;

    router.post(
        `/admin/inventory/adjust_inventory/${batchItemId}`,
        {
            new_quantity: newQuantity,
            reason: adjustReasons.value[batchItemId] || 'Điều chỉnh tồn kho lô hàng thủ công',
        },
        {
            onSuccess: () => {
                cancelEditingBatchItem(batchItemId);
            },
            onError: (errors) => {
                console.error('Validation errors:', errors);
            },
            onFinish: () => {
                isLoading.value = false;
            },
        },
    );
}
</script>

<template>
    <Head title="Chi tiết tồn kho" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <FlashMessage />
        <div class="no-print min-h-screen bg-gray-50 p-6">
            <div class="mx-auto max-w-7xl space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <button @click="goBack" class="h-9 w-9 rounded border border-gray-300 bg-white text-gray-700 hover:border-gray-400">←</button>
                        <div>
                            <h1 class="text-xl font-bold text-gray-800">{{ props.product.name }}</h1>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <div class="flex flex-col gap-6 lg:col-span-2">
                        <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                            <div class="border-b border-gray-200 p-4">
                                <h2 class="text-lg font-semibold">Thông tin sản phẩm</h2>
                            </div>
                            <div class="grid grid-cols-1 gap-6 p-6 md:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Tên sản phẩm <span class="text-red-500">*</span></label>
                                    <input
                                        type="text"
                                        class="mt-4 block w-full rounded-md border-gray-300 px-2 py-2 shadow-sm"
                                        :value="props.product.name"
                                        disabled
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Mã SKU</label>
                                    <input
                                        type="text"
                                        class="mt-1 block w-full rounded-md border-gray-300 px-2 py-2 shadow-sm"
                                        :value="props.product.sku"
                                        disabled
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Mã vạch/Barcode</label>
                                    <input
                                        type="text"
                                        class="mt-1 block w-full rounded-md border-gray-300 px-2 py-2 shadow-sm"
                                        :value="props.product.barcode"
                                        disabled
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Nhập đơn vị tính</label>
                                    <input
                                        type="text"
                                        class="mt-1 block w-full rounded-md border-gray-300 px-2 py-2 shadow-sm"
                                        :value="props.unit.name"
                                        disabled
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Giá bán</label>
                                    <input
                                        type="text"
                                        :value="formatCurrency(props.product.selling_price)"
                                        disabled
                                        class="mt-1 block w-full rounded-md border-gray-300 bg-gray-50 px-2 py-2 shadow-sm"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Giá vốn</label>
                                    <input
                                        type="text"
                                        value="Đang cập nhật"
                                        disabled
                                        class="mt-1 block w-full rounded-md border-gray-300 bg-gray-50 px-2 py-2 text-gray-400 shadow-sm"
                                    />
                                </div>
                                <div class="md:col-span-2">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Mô tả</label>
                                        <textarea
                                            class="mt-2 block w-full rounded-md border-gray-300 px-2 py-2 shadow-sm"
                                            :value="props.product.description"
                                            rows="4"
                                            disabled
                                        ></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-col gap-6">
                        <!-- Ảnh sản phẩm + danh mục -->
                        <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                            <div class="space-y-4 p-6">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Ảnh sản phẩm</label>
                                    <div v-if="props.product">
                                        <img :src="props.product.image_url" alt="Ảnh sản phẩm" class="h-20 w-20 object-cover" />
                                    </div>
                                    <div v-else class="text-sm text-gray-500">Không có ảnh</div>
                                </div>

                                <!-- Danh mục -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Danh mục</label>
                                    <input
                                        type="text"
                                        :value="props.category.name"
                                        disabled
                                        class="mt-1 block w-full rounded-md border-gray-300 bg-gray-50 px-2 py-2 shadow-sm"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Giá bán và giá vốn -->
                        <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                            <div class="border-b border-gray-200 p-4">
                                <h2 class="text-lg font-semibold text-gray-800">Thông tin tồn kho</h2>
                            </div>
                            <div class="space-y-4 p-6">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Tổng tồn kho</label>
                                    <input
                                        type="number"
                                        class="block w-full rounded-md border-gray-300 px-2 py-2 shadow-sm"
                                        :value="props.product.stock_quantity"
                                        disabled
                                    />
                                    <p class="mt-1 text-sm text-gray-500">Để chỉnh sửa tồn kho, vui lòng điều chỉnh từng lô hàng bên dưới</p>
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Có thể bán</label>
                                    <input
                                        type="text"
                                        class="block w-full rounded-md border-gray-300 px-2 py-2 shadow-sm"
                                        :value="availableProducts.find((ap) => ap.product_id === props.product.id)?.value || 0"
                                        disabled
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <div class="flex flex-col gap-6 lg:col-span-2">
                        <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                            <div class="flex items-center justify-between border-b border-gray-200 p-4">
                                <h2 class="text-lg font-semibold">Thông tin lô hàng</h2>
                            </div>
                            <div class="grid grid-cols-1 gap-6 p-6 md:grid-cols-2">
                                <div class="md:col-span-2">
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th
                                                        scope="col"
                                                        class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                                    >
                                                        Mã lô
                                                    </th>
                                                    <th
                                                        scope="col"
                                                        class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                                    >
                                                        Trạng thái
                                                    </th>
                                                    <th
                                                        scope="col"
                                                        class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                                    >
                                                        Ngày sản xuất
                                                    </th>
                                                    <th
                                                        scope="col"
                                                        class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                                    >
                                                        Hạn sử dụng
                                                    </th>
                                                    <th
                                                        scope="col"
                                                        class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                                    >
                                                        Tồn kho
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200 bg-white">
                                                <tr v-if="!Array.isArray(props.batchItems) || props.batchItems.length === 0">
                                                    <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">Không có dữ liệu lô hàng</td>
                                                </tr>
                                                <template v-else>
                                                    <tr v-for="item in props.batchItems" :key="item.id" class="hover:bg-gray-50">
                                                        <td class="px-6 py-4 text-sm font-medium whitespace-nowrap text-gray-900">
                                                            {{ getBatchNumber(item.batch_id) }}
                                                        </td>
                                                        <td class="px-6 py-4 text-sm whitespace-nowrap text-gray-500">
                                                            <span
                                                                :class="{
                                                                    'inline-flex rounded-full px-2 py-1 text-xs leading-5 font-semibold': true,
                                                                    'bg-green-100 text-green-800': item.inventory_status === 'active',
                                                                    'bg-yellow-100 text-yellow-800': item.inventory_status === 'low_stock',
                                                                    'bg-orange-100 text-orange-800': item.inventory_status === 'expiring_soon',
                                                                    'bg-red-100 text-red-800':
                                                                        item.inventory_status === 'out_of_stock' ||
                                                                        item.inventory_status === 'expired',
                                                                    'bg-gray-100 text-gray-800': item.inventory_status === 'damaged',
                                                                }"
                                                            >
                                                                {{
                                                                    item.inventory_status === 'active'
                                                                        ? 'Còn hàng'
                                                                        : item.inventory_status === 'low_stock'
                                                                          ? 'Sắp hết'
                                                                          : item.inventory_status === 'expiring_soon'
                                                                            ? 'Sắp hết hạn'
                                                                            : item.inventory_status === 'out_of_stock'
                                                                              ? 'Hết hàng'
                                                                              : item.inventory_status === 'expired'
                                                                                ? 'Hết hạn'
                                                                                : 'Hư hỏng'
                                                                }}
                                                            </span>
                                                        </td>
                                                        <td class="px-6 py-4 text-sm whitespace-nowrap text-gray-500">
                                                            {{
                                                                item.manufacturing_date
                                                                    ? new Date(item.manufacturing_date).toLocaleDateString('vi-VN')
                                                                    : 'N/A'
                                                            }}
                                                        </td>
                                                        <td class="px-6 py-4 text-sm whitespace-nowrap text-gray-500">
                                                            {{ item.expiry_date ? new Date(item.expiry_date).toLocaleDateString('vi-VN') : 'N/A' }}
                                                        </td>
                                                        <td class="px-6 py-4 text-sm whitespace-nowrap text-gray-500">
                                                            <div v-if="!editingBatchItems[item.id]" class="flex items-center gap-2">
                                                                <span>{{ item.current_quantity }}</span>
                                                                <button
                                                                    @click="startEditingBatchItem(item.id, item.current_quantity)"
                                                                    class="rounded bg-blue-500 px-2 py-1 text-xs text-white hover:bg-blue-600 focus:ring-1 focus:ring-blue-500 focus:outline-none"
                                                                >
                                                                    Sửa
                                                                </button>
                                                            </div>
                                                            <div v-else class="space-y-2">
                                                                <input
                                                                    :value="newQuantities[item.id]"
                                                                    @input="updateQuantity(item.id, ($event.target as HTMLInputElement).value)"
                                                                    type="number"
                                                                    min="0"
                                                                    class="w-20 rounded border-gray-300 px-2 py-1 text-sm focus:border-blue-500 focus:ring-blue-500"
                                                                />
                                                                <input
                                                                    :value="adjustReasons[item.id]"
                                                                    @input="updateReason(item.id, ($event.target as HTMLInputElement).value)"
                                                                    type="text"
                                                                    placeholder="Lý do"
                                                                    class="w-full rounded border-gray-300 px-2 py-1 text-sm focus:border-blue-500 focus:ring-blue-500"
                                                                />
                                                                <div class="flex gap-1">
                                                                    <button
                                                                        @click="saveBatchItemAdjustment(item.id, item.current_quantity)"
                                                                        :disabled="isLoading"
                                                                        class="rounded bg-green-500 px-2 py-1 text-xs text-white hover:bg-green-600 focus:ring-1 focus:ring-green-500 focus:outline-none disabled:bg-gray-400"
                                                                    >
                                                                        {{ isLoading ? 'Lưu...' : 'Lưu' }}
                                                                    </button>
                                                                    <button
                                                                        @click="cancelEditingBatchItem(item.id)"
                                                                        :disabled="isLoading"
                                                                        class="rounded bg-gray-500 px-2 py-1 text-xs text-white hover:bg-gray-600 focus:ring-1 focus:ring-gray-500 focus:outline-none disabled:bg-gray-400"
                                                                    >
                                                                        Hủy
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
