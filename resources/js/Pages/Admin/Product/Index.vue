<script setup>
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    products: { type: Object, required: true },
    filters: { type: Object, required: true },
    categoryOptions: { type: Array, required: true },
    statusOptions: { type: Array, required: true },
});

const search = reactive({
    keyword: props.filters.keyword ?? '',
    category_id: props.filters.category_id ?? '',
    status: props.filters.status ?? '',
});

const apply = () => router.get('/admin/products', { ...search }, {
    preserveState: true,
    replace: true,
});

const reset = () => {
    search.keyword = '';
    search.category_id = '';
    search.status = '';
    apply();
};

const won = (n) => `${Number(n ?? 0).toLocaleString('ko-KR')}원`;

const inputClass = 'rounded-lg border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm outline-none focus:border-neutral-400';
</script>

<template>
    <AdminLayout title="상품목록">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold tracking-tight">상품</h2>
                <p class="mt-1 text-sm text-neutral-500">
                    재고는 조합(SKU)별로 관리합니다. 목록의 수량은 조합 합계입니다.
                </p>
            </div>

            <Link
                href="/admin/products/create"
                class="rounded-lg bg-neutral-100 px-3 py-2 text-sm font-medium text-neutral-900 transition hover:bg-white"
            >
                상품 등록
            </Link>
        </div>

        <form class="mt-6 flex flex-wrap items-center gap-2" @submit.prevent="apply">
            <input v-model="search.keyword" type="text" placeholder="상품명 검색" :class="inputClass">

            <select v-model="search.category_id" :class="inputClass">
                <option value="">전체 카테고리</option>
                <option v-for="c in categoryOptions" :key="c.id" :value="c.id">{{ c.label }}</option>
            </select>

            <select v-model="search.status" :class="inputClass">
                <option value="">전체 상태</option>
                <option v-for="s in statusOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>

            <button type="submit" class="rounded-lg bg-neutral-100 px-3 py-2 text-sm font-medium text-neutral-900">
                검색
            </button>
            <button type="button" class="rounded-lg border border-neutral-700 px-3 py-2 text-sm text-neutral-300" @click="reset">
                초기화
            </button>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-[44rem] mt-6 w-full text-sm">
                <thead class="border-b border-neutral-800 text-left text-neutral-500">
                    <tr>
                        <th class="py-2 font-medium">상품명</th>
                        <th class="py-2 font-medium">카테고리</th>
                        <th class="py-2 text-right font-medium">판매가</th>
                        <th class="py-2 text-center font-medium">재고</th>
                        <th class="py-2 text-center font-medium">예약</th>
                        <th class="py-2 text-center font-medium">판매가능</th>
                        <th class="py-2 text-center font-medium">상태</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in products.data" :key="p.id" class="border-b border-neutral-900">
                        <td class="py-3">
                            <Link :href="`/admin/products/${p.id}/edit`" class="flex items-center gap-3 hover:underline">
                                <img
                                    v-if="p.thumbnail_url"
                                    :src="p.thumbnail_url"
                                    alt=""
                                    class="h-10 w-10 shrink-0 rounded object-cover"
                                >
                                <span
                                    v-else
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded bg-neutral-900 text-xs text-neutral-700"
                                >
                                    없음
                                </span>
                                {{ p.name }}
                            </Link>
                        </td>
                        <td class="py-3 text-neutral-400">{{ p.category_name }}</td>
                        <td class="py-3 text-right">
                            <span>{{ won(p.display_price) }}</span>
                            <span v-if="p.sale_price !== null" class="ml-2 text-xs text-neutral-600 line-through">
                                {{ won(p.base_price) }}
                            </span>
                        </td>
                        <td class="py-3 text-center text-neutral-400">{{ p.stock_total }}</td>
                        <td class="py-3 text-center" :class="p.reserved_total > 0 ? 'text-amber-300' : 'text-neutral-600'">
                            {{ p.reserved_total }}
                        </td>
                        <td class="py-3 text-center" :class="p.available_total <= 0 ? 'text-red-400' : ''">
                            {{ p.available_total }}
                        </td>
                        <td class="py-3 text-center">
                            <span class="rounded bg-neutral-800 px-1.5 py-0.5 text-xs text-neutral-300">
                                {{ p.status_label }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p v-if="products.data.length === 0" class="mt-6 text-sm text-neutral-500">
            상품이 없습니다.
        </p>

        <div v-if="products.last_page > 1" class="mt-6 flex gap-1">
            <Link
                v-for="link in products.links"
                :key="link.label"
                :href="link.url ?? '#'"
                class="rounded px-3 py-1 text-sm"
                :class="link.active ? 'bg-neutral-100 text-neutral-900' : 'text-neutral-400 hover:bg-neutral-900'"
                v-html="link.label"
            />
        </div>
    </AdminLayout>
</template>
