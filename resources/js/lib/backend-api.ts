/**
 * BackendApi — HTTP Client สำหรับเรียก Backend API ผ่าน BFF Proxy
 *
 * เรียกผ่าน erped server (same-origin) ไม่ส่ง token ไป client
 * Token อยู่ใน session (server-side) เท่านั้น
 *
 * Flow:
 *  Browser → erped API (same-origin) → pppportal API
 *  ไม่มี token ใน JavaScript — ปลอดภัยจาก XSS token theft
 */

// ─── API Response Types ─────────────────────────────────────────

export interface ApiResponse<T = unknown> {
    success: boolean;
    message: string;
    data: T;
    status: number;
}

export interface ApiError {
    success: false;
    message: string;
    errors?: Record<string, string[]>;
    status: number;
}

// ─── Core Fetch Wrapper ─────────────────────────────────────────

async function request<T>(
    method: string,
    endpoint: string,
    options: {
        body?: Record<string, unknown>;
        query?: Record<string, string>;
    } = {},
): Promise<ApiResponse<T>> {
    // เรียกผ่าน BFF proxy (same-origin) — ไม่ต้องส่ง token
    const url = new URL(`/api/v1/proxy${endpoint}`, window.location.origin);

    if (options.query) {
        Object.entries(options.query).forEach(([key, value]) => {
            url.searchParams.set(key, value);
        });
    }

    const headers: Record<string, string> = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    // ส่ง CSRF token (Laravel session auth)
    const csrfToken = document.querySelector<HTMLMetaElement>(
        'meta[name="csrf-token"]',
    )?.content;
    if (csrfToken) {
        headers['X-CSRF-TOKEN'] = csrfToken;
    }

    const fetchOptions: RequestInit = {
        method,
        headers,
        credentials: 'same-origin',
    };

    if (options.body && method !== 'GET') {
        fetchOptions.body = JSON.stringify(options.body);
    }

    const response = await fetch(url.toString(), fetchOptions);

    // 401 = session หมดอายุ → redirect ไป login
    if (response.status === 401) {
        window.location.href = '/login';
        throw { success: false, message: 'Session expired', status: 401 } as ApiError;
    }

    const json = await response.json();

    if (!response.ok) {
        const error: ApiError = {
            success: false,
            message: json.message ?? 'เกิดข้อผิดพลาด',
            errors: json.errors,
            status: response.status,
        };
        throw error;
    }

    return json as ApiResponse<T>;
}

// ─── Public API Methods ─────────────────────────────────────────

export const backendApi = {
    get: <T>(endpoint: string, query?: Record<string, string>) =>
        request<T>('GET', endpoint, { query }),

    post: <T>(endpoint: string, body?: Record<string, unknown>) =>
        request<T>('POST', endpoint, { body }),

    put: <T>(endpoint: string, body?: Record<string, unknown>) =>
        request<T>('PUT', endpoint, { body }),

    patch: <T>(endpoint: string, body?: Record<string, unknown>) =>
        request<T>('PATCH', endpoint, { body }),

    delete: <T>(endpoint: string, body?: Record<string, unknown>) =>
        request<T>('DELETE', endpoint, { body }),
} as const;
