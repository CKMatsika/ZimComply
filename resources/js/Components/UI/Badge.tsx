import React from 'react';

export type BadgeVariant = 'success' | 'warning' | 'danger' | 'info' | 'neutral' | 'teal';

interface BadgeProps {
    children: React.ReactNode;
    variant?: BadgeVariant;
    size?: 'sm' | 'md';
    dot?: boolean;
    className?: string;
}

export function Badge({
    children,
    variant = 'neutral',
    size = 'sm',
    dot = false,
    className = '',
}: BadgeProps) {
    const variantStyles: Record<BadgeVariant, { container: string; dot: string }> = {
        success: {
            container: 'bg-emerald-50 text-emerald-800 border-emerald-200',
            dot: 'bg-emerald-600',
        },
        teal: {
            container: 'bg-[#e2f4f2] text-[#125752] border-[#a5dcd6]',
            dot: 'bg-[#19766F]',
        },
        warning: {
            container: 'bg-[#fdf8ee] text-[#8e6015] border-[#f4deba]',
            dot: 'bg-[#D8A34B]',
        },
        danger: {
            container: 'bg-[#fdf2f2] text-[#933632] border-[#f5c7c5]',
            dot: 'bg-[#B84B46]',
        },
        info: {
            container: 'bg-sky-50 text-sky-800 border-sky-200',
            dot: 'bg-sky-600',
        },
        neutral: {
            container: 'bg-slate-100 text-slate-700 border-slate-200',
            dot: 'bg-slate-500',
        },
    };

    const sizeStyles = {
        sm: 'text-[11px] px-2 py-0.5 font-medium tracking-tight',
        md: 'text-xs px-2.5 py-1 font-semibold',
    };

    const currentVariant = variantStyles[variant];

    return (
        <span
            className={`inline-flex items-center gap-1.5 rounded-md border ${currentVariant.container} ${sizeStyles[size]} ${className}`}
        >
            {dot && <span className={`h-1.5 w-1.5 rounded-full shrink-0 ${currentVariant.dot}`} />}
            <span>{children}</span>
        </span>
    );
}
