<script setup lang="ts">
/**
 * Site footer. Public pages only — the dashboard deliberately omits it so the
 * sidebar/content area owns the full viewport height.
 */
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth.user);
const appName = computed(() => page.props.appName ?? 'Marketplace');
const initial = computed(() => (appName.value.charAt(0) || 'M').toUpperCase());
const year = new Date().getFullYear();
</script>

<template>
    <footer class="mt-8 bg-slate-900 text-slate-400">
        <!--
            pb-20 below md clears the fixed mobile bottom-nav. `main` also
            carries pb-20, but the footer is a *sibling* of main, so that
            padding does nothing here and the copyright line was covered.
        -->
        <div class="mx-auto max-w-7xl px-3 pb-20 pt-10 sm:px-4 md:pb-4 lg:px-4">
            <div class="grid grid-cols-2 gap-8 md:grid-cols-5">
                <!-- Brand -->
                <div class="col-span-2">
                    <Link :href="route('home')" class="mb-3 flex items-center gap-2">
                        <span class="grid h-9 w-9 place-items-center rounded-xl bg-brand-500 font-bold text-white">{{ initial }}</span>
                        <span class="text-lg font-bold text-white">{{ appName }}</span>
                    </Link>
                    <p class="max-w-xs text-sm text-slate-400">
                        Bangladesh's trusted marketplace for buying and selling digital assets — social pages,
                        websites, domains, and software.
                    </p>
                    <div class="mt-3 flex items-center gap-2">
                        <span class="inline-flex items-center gap-1 rounded-full border border-brand-800 bg-brand-900/50 px-2 py-1 text-xs font-medium text-brand-400">
                            <span class="h-1.5 w-1.5 rounded-full bg-brand-400"></span> Secure &amp; Moderated
                        </span>
                    </div>
                </div>

                <!-- Marketplace -->
                <div>
                    <h4 class="mb-3 text-sm font-semibold text-white">Marketplace</h4>
                    <ul class="flex flex-col gap-2 text-sm">
                        <li><Link :href="route('marketplace.index')" class="text-slate-400 hover:text-white">Browse Listings</Link></li>
                        <li><Link :href="`${route('marketplace.index')}#categories`" class="text-slate-400 hover:text-white">Categories</Link></li>
                        <li><Link :href="route('faq')" class="text-slate-400 hover:text-white">How It Works</Link></li>
                        <li><Link :href="route('contact')" class="text-slate-400 hover:text-white">Contact Support</Link></li>
                    </ul>
                </div>

                <!-- Sellers -->
                <div>
                    <h4 class="mb-3 text-sm font-semibold text-white">Sellers</h4>
                    <ul class="flex flex-col gap-2 text-sm">
                        <li v-if="user?.can_sell">
                            <Link :href="route('dashboard.listings.create')" class="text-slate-400 hover:text-white">Create Listing</Link>
                        </li>
                        <li v-if="!user"><Link :href="route('register')" class="text-slate-400 hover:text-white">Become a Seller</Link></li>
                        <li><Link :href="route('legal', 'seller-policy')" class="text-slate-400 hover:text-white">Seller Policy</Link></li>
                        <li><Link :href="route('legal', 'prohibited-assets')" class="text-slate-400 hover:text-white">Prohibited Assets</Link></li>
                    </ul>
                </div>

                <!-- Legal -->
                <div>
                    <h4 class="mb-3 text-sm font-semibold text-white">Legal</h4>
                    <ul class="flex flex-col gap-2 text-sm">
                        <li><Link :href="route('legal', 'buyer-protection')" class="text-slate-400 hover:text-white">Buyer Protection</Link></li>
                        <li><Link :href="route('legal', 'refund-policy')" class="text-slate-400 hover:text-white">Refund Policy</Link></li>
                        <li><Link :href="route('legal', 'dispute-policy')" class="text-slate-400 hover:text-white">Dispute Policy</Link></li>
                        <li><Link :href="route('legal', 'terms')" class="text-slate-400 hover:text-white">Terms of Service</Link></li>
                        <li><Link :href="route('legal', 'privacy')" class="text-slate-400 hover:text-white">Privacy Policy</Link></li>
                    </ul>
                </div>
            </div>

            <!-- Bottom bar -->
            <div class="mt-10 flex flex-col items-center justify-between gap-3 border-t border-slate-800 pt-4 text-xs text-slate-500 sm:flex-row">
                <p>© {{ year }} {{ appName }}. All rights reserved.</p>
                <p>Payouts in <span class="money font-medium text-slate-400">৳ BDT</span> · bKash · Nagad · Rocket · Upay</p>
            </div>
        </div>
    </footer>
</template>
