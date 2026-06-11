<template>
  <div class="grid gap-3 md:grid-cols-3 xl:grid-cols-6">
    <div v-for="item in 6" :key="item" class="h-28 animate-pulse rounded-md border bg-white p-4">
      <div class="h-3 w-24 rounded bg-slate-200" />
      <div class="mt-5 h-7 w-20 rounded bg-slate-200" />
      <div class="mt-3 h-3 w-28 rounded bg-slate-100" />
    </div>
  </div>
</template>
