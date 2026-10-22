export type Session = {
    name: string;
    mode: string;
    instructor: string;
    room: string;
    starts_at: string;
    ends_at: string;
    duration_minutes: number;
    training_type_id: number;
};
export type Preview = {
    can_cancel: boolean;
    can_move: boolean;
    returns_credit: boolean;
    cancellation_deadline: string;
    timezone: string;
    message: string;
};
export type ScheduleItem = Session & {
    key: string;
    target: {
        class_session_id?: number;
        availability_rule_id?: number;
        starts_at?: string;
    };
    capacity: number;
    remaining: number;
    available: boolean;
    has_credit?: boolean;
    unavailable_reason: string | null;
    cancellation_minutes: number;
};
export type ScheduleProps = {
    filters: { date: string; type: number | null; instructor: number | null };
    timezone: string;
    items: ScheduleItem[];
    types: { id: number; name: string }[];
    instructors: { id: number; name: string }[];
    notice: string | null;
    authenticated: boolean;
    intent: string | null;
    moving: { id: number; preview: Preview } | null;
    operationKey: string;
};
export type Paginated<T> = {
    data: T[];
    prev_page_url: string | null;
    next_page_url: string | null;
};
export type Booking = { id: number; status: string; session: Session };
export type PortalProps = {
    timezone: string;
    period: string;
    operationKey: string;
    bookings: Paginated<Booking>;
    selected: (Booking & { credits_used: number; preview: Preview }) | null;
    grants: Paginated<{
        id: number;
        name: string;
        source: string;
        valid_from: string;
        expires_at: string;
        types: string[];
        balance: {
            available: number;
            reserved: number;
            expired: number;
            pending: number;
        };
    }>;
    entries: Paginated<{
        id: number;
        credit_grant_id: number;
        booking_id: number | null;
        kind: string;
        amount: number;
        occurred_at: string;
    }>;
};
