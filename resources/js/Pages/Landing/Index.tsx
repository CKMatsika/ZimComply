import React from 'react';
import { Head } from '@inertiajs/react';
import { Navbar } from './Navbar';
import { Hero } from './Hero';
import { ProblemSection } from './ProblemSection';
import { SolutionSection } from './SolutionSection';
import { HowItWorks } from './HowItWorks';
import { WhoItsFor } from './WhoItsFor';
import { TrustSection } from './TrustSection';
import { FaqSection } from './FaqSection';
import { FinalCta } from './FinalCta';
import { Footer } from './Footer';

interface LandingProps {
    auth?: {
        user?: {
            id: string;
            name: string;
            email: string;
        } | null;
    };
}

export default function Landing({ auth }: LandingProps) {
    return (
        <div className="min-h-screen bg-white text-slate-900 flex flex-col font-sans selection:bg-[#19766F] selection:text-white">
            <Head>
                <title>ZimComply AI — Zimbabwe Business Compliance Platform</title>
                <meta
                    name="description"
                    content="From company registration to tax, employment, licences and tender readiness, ZimComply helps Zimbabwean businesses understand their obligations, organise their records and stay ahead of important deadlines."
                />
                <meta name="keywords" content="Zimbabwe company registration, CIPZ, ZIMRA tax clearance, NSSA, ZIMDEF, PRAZ tender readiness, compliance management Zimbabwe" />
                <meta property="og:title" content="ZimComply AI — Running a business is hard enough. Compliance shouldn't be." />
                <meta property="og:description" content="Structured compliance orchestration and registration roadmap software engineered for Zimbabwean businesses." />
                <meta property="og:type" content="website" />
            </Head>

            {/* Public Navigation */}
            <Navbar auth={auth} />

            {/* Main Content Sections */}
            <main className="flex-1" id="main-content">
                <Hero />
                <ProblemSection />
                <SolutionSection />
                <HowItWorks />
                <WhoItsFor />
                <TrustSection />
                <FaqSection />
                <FinalCta />
            </main>

            {/* Public Footer */}
            <Footer />
        </div>
    );
}
