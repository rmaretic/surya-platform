export type OperationalMetrics = {
    sessions: number;
    cancelled: number;
    capacity: number;
    confirmed: number;
    available: number;
    pending: number;
    present: number;
    no_show: number;
    late_cancelled: number;
    occupancy: number | null;
};

export type ReportPage<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
};

export type OperationalDashboardData = {
    period: { from: string; to: string; timezone: string };
    summary: OperationalMetrics;
    scope?: 'own' | 'studio';
    sessions?: ReportPage<{
        id: number;
        name: string;
        status: string;
        local_start: string;
        capacity: number;
        confirmed: number;
        available: number | null;
        pending: number;
        present: number;
        no_show: number;
        late_cancelled: number;
    }>;
    tenants?: ReportPage<{
        id: number;
        name: string;
        status: string;
        metrics: OperationalMetrics;
    }>;
};
