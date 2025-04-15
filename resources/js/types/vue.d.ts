declare module '*.vue' {
    import type { DefineComponent } from 'vue'
    const component: DefineComponent<{}, {}, any>
    export default component
}

declare module '@inertiajs/vue3' {
    import type { DefineComponent } from 'vue'
    export const Link: DefineComponent<{}, {}, any>
    export const Head: DefineComponent<{}, {}, any>
    export const useForm: any
    export const usePage: any
}

declare module '@inertiajs/core' {
    export const router: any
    export const page: any
} 