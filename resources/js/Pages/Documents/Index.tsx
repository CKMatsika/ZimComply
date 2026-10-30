import React, { useState } from 'react';
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
    Search,
    Filter,
    Shield,
    Lock,
    X,
    FileCheck,
    AlertTriangle,
} from 'lucide-react';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/Components/UI/Card';
import { Badge } from '@/Components/UI/Badge';
import { Button } from '@/Components/UI/Button';

export default function DocumentsIndex() {
    const [selectedCategory, setSelectedCategory] = useState<'ALL' | 'CORPORATE' | 'TAX' | 'LABOUR' | 'LOCAL'>('ALL');
    const [searchQuery, setSearchQuery] = useState('');
    const [uploadModalOpen, setUploadModalOpen] = useState(false);

    const documents = [
        {
            id: 'doc-1',
            title: 'Certificate of Incorporation (CR14 / CR6)',
            category: 'CORPORATE',
            regulator: 'Companies Registry (COBE Act [Chapter 24:31])',
            filename: 'cobe_incorporation_certificate_2023.pdf',
            size: '1.4 MB',
            uploadedAt: '15 Oct 2026',
            expiresAt: 'Perpetual',
            evidenceTier: 'DOCUMENT_SUPPORTED',
            evidenceTierLabel: 'Tier 2: Document Supported',
            evidenceVariant: 'teal' as const,
            verificationStatus: 'APPROVED_BY_HUMAN',
            verificationLabel: 'Human Verified',
            hash: 'sha256:8f4b1e9c20a891f0384a29a',
        },
        {
            id: 'doc-2',
            title: 'ZIMRA Tax Clearance Certificate (ITF263)',
            category: 'TAX',
            regulator: 'Zimbabwe Revenue Authority (Section 80)',
            filename: 'zimra_itf263_clearance_2026.pdf',
            size: '890 KB',
            uploadedAt: '20 Oct 2026',
            expiresAt: '31 Dec 2026',
            evidenceTier: 'DOCUMENT_SUPPORTED',
            evidenceTierLabel: 'Tier 2: Document Supported',
            evidenceVariant: 'teal' as const,
            verificationStatus: 'PENDING_HUMAN_REVIEW',
            verificationLabel: 'Review Pending',
            hash: 'sha256:1a79c3882f091ab54cf8',
        },
        {
            id: 'doc-3',
            title: 'NSSA Compliance Clearance Certificate',
            category: 'LABOUR',
            regulator: 'National Social Security Authority (NSSA Act)',
            filename: 'nssa_clearance_oct2026.pdf',
            size: '720 KB',
            uploadedAt: '24 Oct 2026',
            expiresAt: '30 Nov 2026',
            evidenceTier: 'VERIFIED_BY_ISSUER',
            evidenceTierLabel: 'Tier 1: Issuer API Verified',
            evidenceVariant: 'success' as const,
            verificationStatus: 'APPROVED_BY_HUMAN',
            verificationLabel: 'Verified Authentic',
            hash: 'sha256:99c2aa4419b0238141b',
        },
        {
            id: 'doc-4',
            title: 'City of Harare Health & Commercial Premises Permit',
            category: 'LOCAL',
            regulator: 'City of Harare Health Department',
            filename: 'coh_health_inspection_receipt.jpg',
            size: '2.1 MB',
            uploadedAt: '28 Oct 2026',
            expiresAt: '31 Dec 2026',
            evidenceTier: 'DOCUMENT_SUPPORTED',
            evidenceTierLabel: 'Tier 2: Document Supported',
            evidenceVariant: 'teal' as const,
            verificationStatus: 'APPROVED_BY_HUMAN',
            verificationLabel: 'Human Verified',
            hash: 'sha256:3d11be779148002991',
        },
    ];

    const filteredDocs = documents.filter((doc) => {
        const matchesCategory = selectedCategory === 'ALL' || doc.category === selectedCategory;
        const matchesSearch = doc.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
            doc.filename.toLowerCase().includes(searchQuery.toLowerCase()) ||
            doc.regulator.toLowerCase().includes(searchQuery.toLowerCase());
        return matchesCategory && matchesSearch;
    });

    return (
        <AppLayout
            title="Compliance Document Vault"
            subtitle="Encrypted, tamper-evident repository for official statutory certificates, licences, clearances, and human verification audits."
        >
            <div className="space-y-6">
                {/* Header Action Bar */}
                <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-2xs flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <span className="text-[10px] font-bold uppercase tracking-wider text-[#19766F]">
                            Tamper-Evident Evidence Vault
                        </span>
                        <h2 className="text-base font-heading font-bold text-slate-900 mt-0.5">
                            Official Statutory Certificates & Regulatory Instruments
                        </h2>
                        <p className="text-xs text-slate-500 mt-0.5">
                            All documents are secured with AES-256 private disk storage and SHA-256 cryptographic hashes.
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button
                            variant="primary"
                            size="sm"
                            onClick={() => setUploadModalOpen(true)}
                            icon={<Upload className="h-4 w-4" />}
                        >
                            Upload Statutory Certificate
                        </Button>
                    </div>
                </div>

                {/* Vault Metrics Strip */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <Card>
                        <CardContent className="p-4">
                            <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Vault Certificates</span>
                            <div className="text-2xl font-heading font-extrabold text-slate-900 mt-1">4 Stored</div>
                            <span className="text-[11px] text-slate-500">Corporate, Tax, Labour, Council</span>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Human Verification Status</span>
                            <div className="text-2xl font-heading font-extrabold text-[#19766F] mt-1">3 Approved</div>
                            <span className="text-[11px] text-slate-500">1 certificate under compliance review</span>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Cryptographic Integrity</span>
                            <div className="text-2xl font-heading font-extrabold text-slate-900 mt-1">100% SHA-256</div>
                            <span className="text-[11px] text-slate-500">Zero checksum discrepancies</span>
                        </CardContent>
                    </Card>
                </div>

                {/* Table-First Documents Registry */}
                <Card>
                    <CardHeader className="flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div className="flex items-center gap-1 bg-slate-100 p-1 rounded-lg">
                            <button
                                type="button"
                                onClick={() => setSelectedCategory('ALL')}
                                className={`px-2.5 py-1 text-xs font-semibold rounded-md transition-colors ${
                                    selectedCategory === 'ALL' ? 'bg-white text-slate-900 shadow-2xs' : 'text-slate-600 hover:text-slate-900'
                                }`}
                            >
                                All Vault Files ({documents.length})
                            </button>
                            <button
                                type="button"
                                onClick={() => setSelectedCategory('CORPORATE')}
                                className={`px-2.5 py-1 text-xs font-semibold rounded-md transition-colors ${
                                    selectedCategory === 'CORPORATE' ? 'bg-white text-slate-900 shadow-2xs' : 'text-slate-600 hover:text-slate-900'
                                }`}
                            >
                                Corporate
                            </button>
                            <button
                                type="button"
                                onClick={() => setSelectedCategory('TAX')}
                                className={`px-2.5 py-1 text-xs font-semibold rounded-md transition-colors ${
                                    selectedCategory === 'TAX' ? 'bg-white text-slate-900 shadow-2xs' : 'text-slate-600 hover:text-slate-900'
                                }`}
                            >
                                Tax & ZIMRA
                            </button>
                            <button
                                type="button"
                                onClick={() => setSelectedCategory('LABOUR')}
                                className={`px-2.5 py-1 text-xs font-semibold rounded-md transition-colors ${
                                    selectedCategory === 'LABOUR' ? 'bg-white text-slate-900 shadow-2xs' : 'text-slate-600 hover:text-slate-900'
                                }`}
                            >
                                Labour & NSSA
                            </button>
                            <button
                                type="button"
                                onClick={() => setSelectedCategory('LOCAL')}
                                className={`px-2.5 py-1 text-xs font-semibold rounded-md transition-colors ${
                                    selectedCategory === 'LOCAL' ? 'bg-white text-slate-900 shadow-2xs' : 'text-slate-600 hover:text-slate-900'
                                }`}
                            >
                                Municipal
                            </button>
                        </div>

                        <div className="relative w-full sm:w-64">
                            <Search className="h-3.5 w-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                            <input
                                type="text"
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                placeholder="Search certificates or hash..."
                                className="w-full pl-8 pr-3 py-1.5 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#19766F] focus:bg-white"
                            />
                        </div>
                    </CardHeader>

                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead className="bg-slate-50 text-[11px] font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">
                                    <tr>
                                        <th className="py-3 px-4">Certificate Name & File</th>
                                        <th className="py-3 px-4">Issuing Authority</th>
                                        <th className="py-3 px-4">Expiry Date</th>
                                        <th className="py-3 px-4">Evidence Tier</th>
                                        <th className="py-3 px-4">Verification Audit</th>
                                        <th className="py-3 px-4">SHA-256 Checksum</th>
                                        <th className="py-3 px-4 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {filteredDocs.map((doc) => (
                                        <tr key={doc.id} className="hover:bg-slate-50/70 transition-colors">
                                            <td className="py-3 px-4">
                                                <div className="font-bold text-slate-900">{doc.title}</div>
                                                <div className="text-[10px] text-slate-500 font-mono flex items-center gap-1.5 mt-0.5">
                                                    <FileText className="h-3 w-3 text-slate-400" />
                                                    <span>{doc.filename} ({doc.size})</span>
                                                </div>
                                            </td>
                                            <td className="py-3 px-4 text-slate-700 max-w-xs">
                                                {doc.regulator}
                                            </td>
                                            <td className="py-3 px-4 text-slate-900 font-semibold whitespace-nowrap">
                                                {doc.expiresAt}
                                            </td>
                                            <td className="py-3 px-4 whitespace-nowrap">
                                                <Badge variant={doc.evidenceVariant} size="sm">
                                                    {doc.evidenceTierLabel}
                                                </Badge>
                                            </td>
                                            <td className="py-3 px-4 whitespace-nowrap">
                                                <Badge
                                                    variant={doc.verificationStatus === 'APPROVED_BY_HUMAN' ? 'success' : 'warning'}
                                                    size="sm"
                                                    dot
                                                >
                                                    {doc.verificationLabel}
                                                </Badge>
                                            </td>
                                            <td className="py-3 px-4 font-mono text-[10px] text-slate-500 whitespace-nowrap">
                                                {doc.hash}
                                            </td>
                                            <td className="py-3 px-4 text-right whitespace-nowrap">
                                                <div className="flex items-center justify-end gap-1.5">
                                                    <Button variant="outline" size="sm" icon={<Eye className="h-3 w-3" />}>
                                                        View
                                                    </Button>
                                                    <Button variant="outline" size="sm" icon={<Download className="h-3 w-3" />}>
                                                        Download
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                {/* Upload Modal Drawer */}
                {uploadModalOpen && (
                    <div className="fixed inset-0 z-50 overflow-y-auto">
                        <div
                            onClick={() => setUploadModalOpen(false)}
                            className="fixed inset-0 bg-slate-900/50 backdrop-blur-xs"
                        />
                        <div className="min-h-full flex items-center justify-center p-4">
                            <div className="relative bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-4">
                                <div className="flex items-center justify-between pb-3 border-b border-slate-100">
                                    <div className="flex items-center gap-2">
                                        <div className="h-8 w-8 rounded-lg bg-[#e2f4f2] text-[#19766F] flex items-center justify-center font-bold">
                                            <Upload className="h-4 w-4" />
                                        </div>
                                        <h3 className="font-heading font-bold text-sm text-slate-900">
                                            Upload Compliance Certificate
                                        </h3>
                                    </div>
                                    <button
                                        onClick={() => setUploadModalOpen(false)}
                                        className="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100"
                                    >
                                        <X className="h-4 w-4" />
                                    </button>
                                </div>

                                <div className="space-y-3 text-xs">
                                    <div>
                                        <label className="block font-bold text-slate-700 uppercase tracking-wider mb-1">
                                            Obligation Category
                                        </label>
                                        <select className="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#19766F]">
                                            <option>ZIMRA Tax Clearance (ITF263)</option>
                                            <option>NSSA Employer Compliance Clearance</option>
                                            <option>Municipal Operating Licence</option>
                                            <option>PRAZ Annual Supplier Certificate</option>
                                            <option>NEC Registration Certificate</option>
                                        </select>
                                    </div>

                                    <div className="p-6 border-2 border-dashed border-slate-300 hover:border-[#19766F] rounded-xl text-center bg-slate-50 cursor-pointer transition-colors">
                                        <Upload className="h-8 w-8 text-slate-400 mx-auto" />
                                        <div className="font-bold text-slate-800 mt-2">Choose file or drag & drop</div>
                                        <div className="text-[10px] text-slate-500 mt-0.5">PDF, PNG, JPG up to 10 MB. SHA-256 calculated automatically.</div>
                                    </div>
                                </div>

                                <div className="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                                    <Button variant="outline" size="sm" onClick={() => setUploadModalOpen(false)}>
                                        Cancel
                                    </Button>
                                    <Button variant="primary" size="sm" onClick={() => setUploadModalOpen(false)}>
                                        Upload & Queue for Human Review
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
