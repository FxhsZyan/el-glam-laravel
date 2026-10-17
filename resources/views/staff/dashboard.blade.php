<x-app-layout>
    <div class="p-6">
        <h1 class="text-2xl font-semibold">Staff dashboard</h1>
        <p>Branch: {{ auth()->user()->branch?->name }}</p>
    </div>
</x-app-layout>