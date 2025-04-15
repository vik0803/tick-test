declare global {
    interface Window {
        __INITIAL_STATE__?: any;
    }
}

export interface User {
    id: number;
    name: string;
    email: string;
    role: string;
    organization_id?: number;
}

export interface Organization {
    id: number;
    name: string;
    settings?: Record<string, any>;
}

export interface Event {
    id: number;
    name: string;
    date: string;
    time: string;
    location: string;
    ticket_prefix: string;
    organization_id: number;
    created_by: number;
    created_at: string;
    updated_at: string;
}

export interface PageProps {
    auth: {
        user: User;
        organization: Organization;
    };
    flash: {
        message?: string;
        type?: 'success' | 'error' | 'warning' | 'info';
    };
    errors: Record<string, string>;
} 