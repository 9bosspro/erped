import { Head, Link } from '@inertiajs/react';

interface ErrorPageProps {
    status: number;
}

const TITLE_BY_STATUS: Record<number, string> = {
    403: 'ไม่มีสิทธิ์เข้าถึง',
    404: 'ไม่พบหน้าที่ค้นหา',
    500: 'เกิดข้อผิดพลาดภายในระบบ',
    503: 'ระบบไม่พร้อมให้บริการ',
};

const DESCRIPTION_BY_STATUS: Record<number, string> = {
    403: 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้',
    404: 'หน้าที่คุณค้นหาไม่มีอยู่ หรือถูกย้ายไปแล้ว',
    500: 'เซิร์ฟเวอร์พบปัญหา กรุณาลองใหม่ในภายหลัง',
    503: 'ระบบกำลังปรับปรุง กรุณากลับมาใหม่ในภายหลัง',
};

export default function ErrorPage({ status }: ErrorPageProps) {
    const title = TITLE_BY_STATUS[status] ?? 'เกิดข้อผิดพลาด';
    const description = DESCRIPTION_BY_STATUS[status] ?? 'มีบางสิ่งผิดพลาด';

    return (
        <>
            <Head title={`${status} — ${title}`} />
            <div className="flex min-h-screen flex-col items-center justify-center gap-4 p-6 text-center">
                <h1 className="text-6xl font-bold tracking-tight">{status}</h1>
                <h2 className="text-2xl font-semibold">{title}</h2>
                <p className="max-w-md text-muted-foreground">{description}</p>
                <Link
                    href="/"
                    className="mt-4 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90"
                >
                    กลับหน้าแรก
                </Link>
            </div>
        </>
    );
}
