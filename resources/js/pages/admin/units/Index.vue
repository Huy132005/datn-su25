<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div
      v-if="showNotification"
      class="fixed top-6 left-1/2 z-50 -translate-x-1/2 rounded-lg bg-green-500 px-6 py-3 text-white shadow-lg text-base font-semibold animate-fade-in-out"
    >
      {{ notification }}
    </div>

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
      <div class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 rounded-xl border md:min-h-min">
        <div class="p-6">
          <div class="mb-4 flex items-center justify-between">
            <h1 class="text-xl font-bold">Quản lý đơn vị tính</h1>
            <Button @click="goToCreatePage" class="rounded-lg bg-green-500 px-6 py-2 text-white hover:bg-green-600 text-sm">
              + Thêm đơn vị
            </Button>
          </div>

          <div class="overflow-hidden rounded-lg bg-white shadow">
  <table class="w-full border-collapse text-sm table-fixed">
    <thead>
      <tr class="bg-gray-100">
        <th class="w-[15%] px-3 py-2 text-center font-semibold">Tên đơn vị</th>
        <th class="w-[65%] px-3 py-2 text-center font-semibold">Mô tả</th>
        <th class="w-[20%] px-3 py-2 text-center font-semibold">Thao tác</th>
      </tr>
    </thead>
    <tbody>
      <tr
        v-for="(unit, index) in paginatedUnits"
        :key="unit.id"
        class="border-t hover:bg-gray-50 transition"
      >
        <td class="px-3 py-2 text-center truncate">{{ unit.name }}</td>
        <td class="px-3 py-2 text-center" style="vertical-align: top;">
          <div class="line-clamp-2 min-h-[48px] overflow-hidden">
            {{ unit.description || '-' }}
          </div>
        </td>
        <td class="px-3 py-2 text-center whitespace-nowrap">
          <Button @click="goToEditPage(unit.id)" class="bg-blue-600 hover:bg-blue-700 text-white rounded px-3 py-1 text-xs">
            Sửa
          </Button>
          <Button @click="deleteUnit(unit.id)" variant="destructive" class="ml-2 px-3 py-1 text-xs rounded">
            Xóa
          </Button>
        </td>
      </tr>
      <tr v-if="!paginatedUnits.length">
        <td colspan="3" class="text-center py-4 text-gray-500">Không có đơn vị tính nào.</td>
      </tr>
    </tbody>
  </table>
</div>



          <!-- Phân trang -->
          <div class="mt-4 flex flex-col items-start gap-2 md:flex-row md:items-center md:justify-between">
            <p class="text-sm">
              Hiển thị từ <span class="font-medium">{{ (currentPage - 1) * perPage + 1 }}</span> đến
              <span class="font-medium">{{ Math.min(currentPage * perPage, total) }}</span> trên
              <span class="font-medium">{{ total }}</span> mục
            </p>

            <div class="flex items-center gap-2">
              <button
                class="px-2 py-1 text-sm text-gray-600 hover:text-black"
                :disabled="currentPage === 1"
                @click="prevPage"
              >
                ← Trước
              </button>
              <template v-for="page in totalPages" :key="page">
                <button
                  class="px-3 py-1 text-sm rounded"
                  :class="page === currentPage ? 'bg-gray-300 font-bold' : 'text-gray-500 hover:text-black'"
                  @click="goToPage(page)"
                >
                  {{ page }}
                </button>
              </template>
              <button
                class="px-2 py-1 text-sm text-gray-600 hover:text-black"
                :disabled="currentPage === totalPages"
                @click="nextPage"
              >
                Sau →
              </button>
            </div>

            <div class="flex items-center gap-1">
              <span class="text-sm">Hiển thị</span>
              <select v-model="perPage" @change="changePerPage" class="border rounded p-1 text-sm">
                <option v-for="opt in perPageOptions" :key="opt" :value="opt">{{ opt }}</option>
              </select>
              <span class="text-sm">kết quả</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>


<script setup>
import AppLayout from '@/layouts/AppLayout.vue'
import { router, usePage } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { ref, computed, onMounted } from 'vue'

const props = defineProps({ units: Array })
const breadcrumbs = [
  { title: 'Quản lý đơn vị tính', href: route('admin.units.index') },
]

const perPageOptions = [5, 10, 25, 50]
const perPage = ref(5)
const currentPage = ref(1)

const total = computed(() => props.units.length)
const totalPages = computed(() => Math.ceil(total.value / perPage.value))

const paginatedUnits = computed(() => {
  const start = (currentPage.value - 1) * perPage.value
  return props.units.slice(start, start + perPage.value)
})

const notification = ref('');
const showNotification = ref(false);
const page = usePage();

function showSimpleNotification(message) {
  notification.value = message;
  showNotification.value = true;
  setTimeout(() => {
    showNotification.value = false;
  }, 2500);
}

function deleteUnit(id) {
  if (confirm('Bạn có chắc chắn muốn xóa đơn vị tính này?')) {
    router.delete(route('admin.units.destroy', id), {
      onSuccess: () => {
        showSimpleNotification('Xóa đơn vị tính thành công');
      },
      onError: (error) => {
        showSimpleNotification(error.message || 'Không thể xóa đơn vị tính này');
      }
    })
  }
}

function goToCreatePage() {
  router.visit(route('admin.units.create'))
}
function goToEditPage(id) {
  router.visit(route('admin.units.edit', id))
}
function goToPage(page) {
  if (page < 1 || page > totalPages.value) return
  currentPage.value = page
}
function prevPage() {
  if (currentPage.value > 1) currentPage.value--
}
function nextPage() {
  if (currentPage.value < totalPages.value) currentPage.value++
}
function changePerPage(event) {
  perPage.value = +(event.target.value)
  currentPage.value = 1
}

onMounted(() => {
  if (page.props.errors && page.props.errors.unit_delete) {
    showSimpleNotification(page.props.errors.unit_delete)
    page.props.errors.unit_delete = undefined
  }
})
</script>

<style scoped>
@keyframes fade-in-out {
  0% { opacity: 0; transform: translateY(-20px) scale(0.95); }
  10% { opacity: 1; transform: translateY(0) scale(1); }
  90% { opacity: 1; transform: translateY(0) scale(1); }
  100% { opacity: 0; transform: translateY(-20px) scale(0.95); }
}
.animate-fade-in-out {
  animation: fade-in-out 2.5s both;
}
</style>
