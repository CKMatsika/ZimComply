import React from 'react';

interface CardProps {
    children: React.ReactNode;
    className?: string;
    onClick?: () => void;
}

export function Card({ children, className = '', onClick }: CardProps) {
    return (
        <div
            onClick={onClick}
            className={`bg-white border border-slate-200/90 rounded-xl shadow-[0_1px_3px_0_rgba(15,23,42,0.03)] ${className}`}
        >
            {children}
        </div>
    );
}

export function CardHeader({ children, className = '' }: CardProps) {
    return (
        <div className={`px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-4 ${className}`}>
            {children}
        </div>
    );
}

export function CardTitle({ children, className = '' }: CardProps) {
    return (
        <h3 className={`font-heading text-sm sm:text-base font-bold text-slate-900 tracking-tight flex items-center gap-2 ${className}`}>
            {children}
        </h3>
    );
}

export function CardDescription({ children, className = '' }: CardProps) {
    return (
        <p className={`text-xs text-slate-500 mt-0.5 leading-relaxed ${className}`}>
            {children}
        </p>
    );
}

export function CardContent({ children, className = '' }: CardProps) {
    return (
        <div className={`p-5 ${className}`}>
            {children}
        </div>
    );
}

export function CardFooter({ children, className = '' }: CardProps) {
    return (
        <div className={`px-5 py-3.5 bg-slate-50/70 border-t border-slate-100 rounded-b-xl flex items-center justify-between text-xs text-slate-500 ${className}`}>
            {children}
        </div>
    );
}
