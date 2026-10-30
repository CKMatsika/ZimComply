import React from 'react';
import AppLayout from '@/Layouts/AppLayout';
import {
    FolderLock,
    Upload,
    FileText,
    ShieldCheck,
    CheckCircle2,
    Clock,
    AlertCircle,
    Download,
    Eye,
    Key,
} from 'lucide-react';

export default function DocumentsIndex() {
    const documents = [
        {
            title: 'Certificate of Incorporation',
            regulator: 'Companies Registry (COBE Act)',
            filename: 'incorporation_cert_cr14.pdf',
            size: '1.4 MB',
            uploadedAt: '15 Oct 2026',
            evidenceTier: 'VERIFIED_BY_ISSUER',
            evidenceColor: 'text-emerald-400 bg-emerald-500/10 border-emerald-500/20',
            hash: 'sha256:8f4b1e...29a',
        },
        {
            title: 'ZIMRA Tax Clearance Certificate (ITF263)',
            regulator: 'ZIMRA',
            filename: 'zimra_itf263_2026.pdf',
            size: '890 KB',
            uploadedAt: '20 Oct 2026',
            evidenceTier: 'DOCUMENT_SUPPORTED',
            evidenceColor: 'text-cyan-400 bg-cyan-500/10 border-cyan-500/20',
            hash: 'sha256:1a79c3...ff8',
        },
        {
            title: 'NSSA Compliance Clearance Certificate',
            regulator: 'NSSA',
            filename: 'nssa_clearance_oct2026.pdf',
            size: '720 KB',
            uploadedAt: '24 Oct 2026',
            evidenceTier: 'VERIFIED_BY_ISSUER',
            evidenceColor: 'text-emerald-400 bg-emerald-500/10 border-emerald-500/20',
            hash: 'sha256:99c2aa...41b',
        },
        {
            title: 'City of Harare Health Inspection Permit',
            regulator: 'City of Harare Health Dept',
            filename: 'coh_health_inspection_receipt.jpg',
            size: '2.1 MB',
            uploadedAt: '28 Oct 2026',
            evidenceTier: 'DOCUMENT_SUPPORTED',
            evidenceColor: 'text-cyan-400 bg-cyan-500/10 border-cyan-500/20',
            hash: 'sha256:3d11be...991',
        },
    ];

    return (
        <AppLayout
            title="Compliance Wallet & Document Vault"
            subtitle="Encrypted, tamper-evident repository for official statutory certificates, licences, and clearances."
        >
            <div className="space-y-6">
                {/* Upload Banner */}
                <div className="glass-panel p-6 rounded-2xl border border-dashed border-slate-700 hover:border-emerald-500/60 transition-colors text-center cursor-pointer">
                    <div className="flex justify-center">
                        <div className="h-12 w-12 rounded-xl bg-slate-900 border border-slate-800 flex items-center justify-center text-emerald-400">
                            <Upload className="h-6 w-6" />
                        </div>
                    </div>
                    <h3 className="mt-3 font-heading font-bold text-white text-base">
                        Upload Statutory Certificate or Permit
                    </h3>
                    <p className="text-xs text-slate-400 mt-1 max-w-md mx-auto">
                        Drag and drop your PDF, PNG, or scanned image. Automatically checked for malware and cryptographic SHA-256 fingerprinting.
                    </p>
                    <div className="mt-3 inline-flex items-center gap-1.5 text-xs text-emerald-400 font-semibold">
                        <span>Select file from computer</span>
                        <span>→</span>
                    </div>
                </div>

                {/* Vault List */}
                <div className="glass-panel p-6 rounded-xl border border-slate-800 space-y-4">
                    <div className="flex items-center justify-between">
                        <h3 className="text-base font-heading font-bold text-white flex items-center gap-2">
                            <FolderLock className="h-4 w-4 text-emerald-400" />
                            Active Vault Documents (4 Stored)
                        </h3>
                        <span className="text-xs text-slate-400">All files encrypted with AES-256</span>
                    </div>

                    <div className="space-y-3">
                        {documents.map((doc, idx) => (
                            <div key={idx} className="glass-card p-4 rounded-xl border border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div className="flex items-start gap-3">
                                    <div className="h-10 w-10 rounded-lg bg-slate-900 border border-slate-800 flex items-center justify-center text-emerald-400 shrink-0 mt-0.5">
                                        <FileText className="h-5 w-5" />
                                    </div>
                                    <div>
                                        <h4 className="font-heading font-bold text-white text-sm">{doc.title}</h4>
                                        <div className="flex items-center gap-2 text-xs text-slate-400 mt-0.5">
                                            <span className="text-emerald-400 font-medium">{doc.regulator}</span>
                                            <span>•</span>
                                            <span>{doc.filename}</span>
                                            <span>•</span>
                                            <span>{doc.size}</span>
                                        </div>
                                        <div className="text-[10px] font-mono text-slate-500 mt-1 flex items-center gap-1">
                                            <Key className="h-3 w-3" />
                                            <span>{doc.hash}</span>
                                        </div>
                                    </div>
                                </div>

                                <div className="flex items-center gap-3 self-end sm:self-center">
                                    <span className={`px-2.5 py-1 rounded text-[11px] font-semibold border ${doc.evidenceColor}`}>
                                        {doc.evidenceTier.replace(/_/g, ' ')}
                                    </span>
                                    <button className="p-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition-colors">
                                        <Eye className="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
