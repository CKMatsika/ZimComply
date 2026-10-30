import React from 'react';
import { Link } from '@inertiajs/react';

export type ButtonVariant = 'primary' | 'secondary' | 'outline' | 'ghost' | 'danger';
export type ButtonSize = 'sm' | 'md' | 'lg';

interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: ButtonVariant;
    size?: ButtonSize;
    href?: string;
    icon?: React.ReactNode;
    children: React.ReactNode;
}

export function Button({
    variant = 'primary',
    size = 'md',
    href,
    icon,
    children,
    className = '',
    disabled,
    ...props
}: ButtonProps) {
    const baseClasses = 'inline-flex items-center justify-center font-medium transition-all rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none cursor-pointer';

    const variantClasses: Record<ButtonVariant, string> = {
        primary: 'bg-[#19766F] hover:bg-[#14605a] text-white shadow-sm border border-[#14605a] focus-visible:ring-[#19766F]',
        secondary: 'bg-[#14263D] hover:bg-[#0e1c2e] text-white shadow-sm border border-[#0e1c2e] focus-visible:ring-[#14263D]',
        outline: 'bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 shadow-xs focus-visible:ring-slate-400',
        ghost: 'hover:bg-slate-100 text-slate-700 focus-visible:ring-slate-400',
        danger: 'bg-[#B84B46] hover:bg-[#9d3f3b] text-white shadow-sm border border-[#9d3f3b] focus-visible:ring-[#B84B46]',
    };

    const sizeClasses: Record<ButtonSize, string> = {
        sm: 'text-xs px-2.5 py-1.5 gap-1.5',
        md: 'text-xs sm:text-sm px-3.5 py-2 gap-2',
        lg: 'text-sm px-4 py-2.5 gap-2.5 font-semibold',
    };

    const combinedClasses = `${baseClasses} ${variantClasses[variant]} ${sizeClasses[size]} ${className}`;

    if (href) {
        return (
            <Link href={href} className={combinedClasses}>
                {icon && <span className="shrink-0">{icon}</span>}
                <span>{children}</span>
            </Link>
        );
    }

    return (
        <button className={combinedClasses} disabled={disabled} {...props}>
            {icon && <span className="shrink-0">{icon}</span>}
            <span>{children}</span>
        </button>
    );
}
