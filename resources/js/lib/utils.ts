import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

/** Merge Tailwind class lists safely (used by shadcn-vue components). */
export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}
