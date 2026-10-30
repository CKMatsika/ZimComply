export interface User {
    id: string;
    name: string;
    email: string;
    phone?: string | null;
    has_mfa: boolean;
}

export interface Tenant {
    id: string;
    name: string;
    type: 'SME' | 'ENTERPRISE' | 'ACCOUNTING_FIRM';
    status: 'TRIAL' | 'ACTIVE' | 'PAST_DUE' | 'CANCELLED';
}

export interface Organisation {
    id: string;
    legal_name: string;
    trading_name?: string | null;
    entity_type: string;
    base_currency: 'USD' | 'ZiG';
}

export interface AuthContext {
    user: User | null;
    active_tenant: Tenant | null;
    active_organisation: Organisation | null;
    available_organisations: Organisation[];
    role?: string | null;
}

export interface PageProps {
    auth: AuthContext;
    flash: {
        success?: string | null;
        error?: string | null;
        info?: string | null;
    };
    [key: string]: any;
}
