<script setup lang="ts">
import FlashMessageHistory from '@/components/FlashMessageHistory.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    product: { id: number; name: string; sku: string };
    transactions: {
        data: {
            id: number;
            transaction_type: { name: string; code: string } | null;
            quantity_change: number;
            stock_after: number;
            unit_price: number | null;
            total_value: number | null;
            transaction_date: string;
            related_bill: { id: number; bill_number: string } | null;
            related_purchase_return: { id: number; return_number: string } | null;
            related_batch: { id: number; batch_number: string } | null;
            user: { id: number; name: string } | null;
            note: string | null;
            created_at: string;
            expiry_date: string | null;
            expiry_status: 'valid' | 'near_expiry' | 'expired' | null;
        }[];
        links: {
            url: string | null;
            label: string;
            active: boolean;
        }[];
    };
}>();

const flashMessages = ref<{ message: string; type: 'success' | 'error' | 'warning' | 'info' }[]>([]);

watch(
    () => props.transactions.data,
    (newTransactions) => {
        flashMessages.value = [];
        newTransactions.forEach((t) => {
            if (t.expiry_status === 'expired') {
                flashMessages.value.push({
                    message: `Sản phẩm ${props.product.name} (Lô: ${t.related_batch?.batch_number ?? '—'}) đã hết hạn sử dụng vào ${formatDate(t.expiry_date)}`,
                    type: 'error',
                });
            } else if (t.expiry_status === 'near_expiry') {
                flashMessages.value.push({
                    message: `Sản phẩm ${props.product.name} (Lô: ${t.related_batch?.batch_number ?? '—'}) sắp hết hạn vào ${formatDate(t.expiry_date)}`,
                    type: 'warning',
                });
            }
        });
    },
    { immediate: true },
);

const formattedTransactions = computed(() =>
    props.transactions.data.map((t) => ({
        id: t.id,
        change_type: t.transaction_type?.name ?? 'Không xác định',
        change_quantity: t.quantity_change,
        stock_after: t.stock_after,
        unit_price: t.unit_price ?? 0,
        total_value: t.total_value ?? 0,
        transaction_date: t.transaction_date,
        related_bill: t.related_bill?.bill_number ?? '—',
        related_purchase_return: t.related_purchase_return?.return_number ?? '—',
        related_batch: t.related_batch?.batch_number ?? '—',
        user: t.user?.name ?? '—',
        note: t.note ?? '—',
        created_at: t.created_at,
    })),
);

function formatDate(dateString: string): string {
    if (!dateString) return '—';
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('vi-VN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    }).format(date);
}

function formatCurrency(value: number): string {
    if (!value) return '0';
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(value);
}

function goBack() {
    router.visit(route('admin.products.index'));
}
</script>

<template>
    <Head title="Lịch sử kho" />
    <AppLayout>
        <div class="min-h-screen bg-gray-50 p-6">
            <div class="mx-auto max-w-7xl space-y-6">
                <div class="flex items-center gap-3">
                    <button @click="goBack" class="h-9 w-9 rounded border border-gray-300 bg-white text-gray-700 hover:border-gray-400">←</button>
                    <h1 class="text-xl font-bold text-gray-800">Lịch sử biến động - {{ props.product.name }} (SKU: {{ props.product.sku }})</h1>
                </div>

                <!-- Hiển thị Flash Messages -->
                <div class="fixed top-4 right-4 z-50 space-y-2">
                    <FlashMessageHistory
                        v-for="(msg, index) in flashMessages"
                        :key="index"
                        :message="msg.message"
                        :type="msg.type"
                        :duration="5000"
                    />
                </div>

                <div v-if="props.transactions.data.length === 0" class="rounded-md border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-800">
                    Chưa có lịch sử biến động nào cho sản phẩm này.
                </div>

                <div v-else class="overflow-x-auto rounded border bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-4 py-2 text-center font-medium text-gray-600">Thời gian</th>
                                <th class="px-4 py-2 text-center font-medium text-gray-600">Loại giao dịch</th>
                                <th class="px-4 py-2 text-center font-medium text-gray-600">Số lượng</th>
                                <th class="px-4 py-2 text-center font-medium text-gray-600">Tồn sau</th>
                                <th class="px-4 py-2 text-center font-medium text-gray-600">Lô hàng</th>
                                <th class="px-4 py-2 text-center font-medium text-gray-600">Người thực hiện</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Ghi chú</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="item in formattedTransactions" :key="item.id" class="hover:bg-gray-50">
                                <td class="px-4 py-2 text-center">{{ formatDate(item.transaction_date) }}</td>
                                <td class="px-4 py-2 text-center">{{ item.change_type }}</td>
                                <td
                                    class="px-4 py-2 text-center"
                                    :class="
                                        item.change_type === 'Nhập kho'
                                            ? 'text-green-600'
                                            : item.change_type === 'Xuất kho'
                                              ? 'text-red-600'
                                              : item.change_type === 'Điều chỉnh'
                                                ? 'text-blue-600'
                                                : 'text-gray-600'
                                    "
                                >
                                    {{ item.change_type === 'Nhập kho'
                                            ? '+'
                                            : item.change_type === 'Xuất kho'
                                              ? '-'
                                              : item.change_type === 'Điều chỉnh'
                                                ? ''
                                                : ''}}{{ item.change_quantity }}
                                </td>
                                <td class="px-4 py-2 text-center">{{ item.stock_after }}</td>
                                <td class="px-4 py-2 text-center">{{ item.related_batch }}</td>
                                <td class="px-4 py-2 text-center">{{ item.user }}</td>
                                <td class="px-4 py-2 text-left">{{ item.note }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="border-t bg-white p-4">
                        <div class="flex items-center justify-between text-sm text-gray-600">
                            <span> Hiển thị {{ props.transactions.data.length }} giao dịch </span>
                            <div class="flex gap-1">
                                <template v-for="link in props.transactions.links" :key="link.label">
                                    <button
                                        v-if="link.url"
                                        @click="router.get(link.url, {}, { preserveState: true, preserveScroll: true })"
                                        :class="[
                                            'rounded px-3 py-1',
                                            link.active ? 'bg-blue-600 text-white' : 'border bg-white text-gray-600 hover:bg-gray-100',
                                        ]"
                                        v-html="link.label"
                                    />
                                    <span v-else class="px-3 py-1 text-gray-400" v-html="link.label" />
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
