export type PublicStudioProfile = {
    name: string;
    short_description: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
};

export type PublicStudioMetadata = {
    title: string;
    description: string;
    canonical: string | null;
    robots: string;
};
