import React from 'react';
import classNames from 'classnames';

export default function NewsletterPreview({ data }) {
  const {
    newsletterName,
    edition,
    issueTitle,
    logoUrl,
    backgroundImage,
    backgroundMode,
    backgroundOpacity,
    leadershipTitle,
    leadershipBodyHtml,
    importantAnnouncementsHtml,
    complianceRemindersHtml,
    safetyRemindersHtml,
    specialMessageHtml,
    usefulInfoHtml,
    winsAndShoutoutsHtml,
    csrHtml,
    employeeEventsHtml,
    quickLinks,
    leadershipImage,
    complianceImage,
    csrImage1,
    csrImage2,
    eventsImage1,
    eventsImage2
  } = data;

  const logoSrc =
    logoUrl && logoUrl.trim().length > 0
      ? logoUrl
      : 'https://via.placeholder.com/180x60?text=Port+City+BPO+Logo';

  const bgStyle =
    backgroundImage
      ? {
          backgroundImage: `url(${backgroundImage})`,
          backgroundRepeat: backgroundMode === 'repeat' ? 'repeat' : 'no-repeat',
          backgroundSize:
            backgroundMode === 'cover'
              ? 'cover'
              : backgroundMode === 'contain'
              ? 'contain'
              : 'auto',
          backgroundPosition: 'center'
        }
      : {};

  return (
    <div className="relative">
      {backgroundImage && (
        <div
          className="absolute inset-0 rounded-2xl pointer-events-none"
          style={{
            ...bgStyle,
            opacity: backgroundOpacity ?? 0.15,
            filter: 'brightness(1)'
          }}
        />
      )}

      <div className="relative bg-white/90 backdrop-blur rounded-2xl shadow-lg border border-slate-200 overflow-hidden w-full max-w-3xl mx-auto">
        <div className="bg-gradient-to-r from-slate-900 to-indigo-600 px-6 py-4 flex items-center justify-between gap-4">
          <div className="flex items-center gap-4">
            <img
              src={logoSrc}
              alt="Port City BPO"
              className="h-10 w-auto rounded bg-white/5 object-contain"
            />
            <div>
              <div className="text-[10px] uppercase tracking-[0.2em] text-slate-200/80">
                {newsletterName}
              </div>
              <div className="text-base font-semibold text-slate-50">
                {issueTitle || 'Storm Safety & Xmas Edition'}
              </div>
            </div>
          </div>
          <div className="text-right text-[11px] text-slate-200/80">
            <div className="font-medium">Edition</div>
            <div>{edition}</div>
          </div>
        </div>

        <div className="p-5 space-y-6 text-sm text-slate-800">
          <section className="rounded-xl bg-indigo-50/90 border border-indigo-100 p-4 flex flex-col md:flex-row gap-4">
            <div className="flex-1">
              <div className="text-[11px] font-semibold uppercase tracking-[0.18em] text-indigo-700 mb-1">
                Message from Leadership
              </div>
              <h2 className="text-base font-semibold text-slate-900 mb-2">
                {leadershipTitle}
              </h2>
              <div
                className="space-y-1 text-sm leading-relaxed prose prose-sm max-w-none"
                dangerouslySetInnerHTML={{ __html: leadershipBodyHtml }}
              />
              <div className="mt-3 text-xs text-slate-600">
                Warm regards,
                <br />
                Gary Seaton
                <br />
                CEO, Port City BPO (Pvt) Ltd.
              </div>
            </div>
            {leadershipImage && (
              <div className="flex-shrink-0 md:w-40">
                <img
                  src={leadershipImage}
                  alt="CEO"
                  className="w-full h-40 object-cover rounded-xl border border-white shadow-sm"
                />
              </div>
            )}
          </section>

          <section className="grid md:grid-cols-2 gap-3">
            <div className="rounded-xl border border-slate-200 bg-white/90 p-3">
              <div className="text-[11px] font-semibold uppercase tracking-[0.18em] text-purple-700 mb-1">
                Important Announcements
              </div>
              <div
                className="prose prose-sm max-w-none text-sm"
                dangerouslySetInnerHTML={{ __html: importantAnnouncementsHtml }}
              />
            </div>
            <div className="rounded-xl border border-red-100 bg-red-50/90 p-3 flex flex-col gap-2">
              <div className="text-[11px] font-semibold uppercase tracking-[0.18em] text-red-700 mb-1">
                Compliance Reminders
              </div>
              <div
                className="prose prose-sm max-w-none text-sm"
                dangerouslySetInnerHTML={{ __html: complianceRemindersHtml }}
              />
              {complianceImage && (
                <img
                  src={complianceImage}
                  alt="Compliance"
                  className="mt-1 w-full h-24 object-cover rounded-lg border border-red-100"
                />
              )}
            </div>
          </section>

          <section className="rounded-xl border border-emerald-200 bg-emerald-50/90 p-4">
            <div className="text-[11px] font-semibold uppercase tracking-[0.18em] text-emerald-700 mb-1">
              Safety Reminders · Storms &amp; Floods
            </div>
            <div
              className="prose prose-sm max-w-none text-sm"
              dangerouslySetInnerHTML={{ __html: safetyRemindersHtml }}
            />
          </section>

          <section className="rounded-xl border border-amber-200 bg-amber-50/90 p-4">
            <div className="text-[11px] font-semibold uppercase tracking-[0.18em] text-amber-700 mb-1">
              Special Message · Xmas Season
            </div>
            <div
              className="prose prose-sm max-w-none text-sm"
              dangerouslySetInnerHTML={{ __html: specialMessageHtml }}
            />
          </section>

          <section className="rounded-xl border border-slate-200 bg-white/90 p-4 space-y-3">
            <div className="flex items-center justify-between gap-2">
              <div className="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-700">
                Our CSR Activities
              </div>
            </div>
            <div className="grid md:grid-cols-3 gap-3">
              <div className="md:col-span-2">
                <div
                  className="prose prose-sm max-w-none text-sm"
                  dangerouslySetInnerHTML={{ __html: csrHtml }}
                />
              </div>
              <div className="space-y-3">
                {csrImage1 && (
                  <img
                    src={csrImage1}
                    alt="CSR 1"
                    className="w-full h-24 object-cover rounded-lg border border-slate-200"
                  />
                )}
                {csrImage2 && (
                  <img
                    src={csrImage2}
                    alt="CSR 2"
                    className="w-full h-24 object-cover rounded-lg border border-slate-200"
                  />
                )}
              </div>
            </div>
          </section>

          <section className="rounded-xl border border-slate-200 bg-white/90 p-4 space-y-3">
            <div className="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-700">
              Employee Events
            </div>
            <div className="grid md:grid-cols-3 gap-3">
              <div className="md:col-span-2">
                <div
                  className="prose prose-sm max-w-none text-sm"
                  dangerouslySetInnerHTML={{ __html: employeeEventsHtml }}
                />
              </div>
              <div className="space-y-3">
                {eventsImage1 && (
                  <img
                    src={eventsImage1}
                    alt="Event 1"
                    className="w-full h-24 object-cover rounded-lg border border-slate-200"
                  />
                )}
                {eventsImage2 && (
                  <img
                    src={eventsImage2}
                    alt="Event 2"
                    className="w-full h-24 object-cover rounded-lg border border-slate-200"
                  />
                )}
              </div>
            </div>
          </section>

          <section className="grid md:grid-cols-2 gap-3">
            <div className="rounded-xl border border-slate-200 bg-slate-50/90 p-3">
              <div className="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-700 mb-1">
                Wins &amp; Shout-outs
              </div>
              <div
                className="prose prose-sm max-w-none text-sm"
                dangerouslySetInnerHTML={{ __html: winsAndShoutoutsHtml }}
              />
            </div>
            <div className="rounded-xl border border-slate-200 bg-slate-50/90 p-3">
              <div className="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-700 mb-1">
                Useful Information
              </div>
              <div
                className="prose prose-sm max-w-none text-sm"
                dangerouslySetInnerHTML={{ __html: usefulInfoHtml }}
              />
            </div>
          </section>

          <section className="rounded-xl border border-indigo-200 bg-indigo-50/90 p-3">
            <div className="text-[11px] font-semibold uppercase tracking-[0.18em] text-indigo-700 mb-1">
              Quick Links
            </div>
            <div className="flex flex-wrap gap-2">
              {quickLinks && quickLinks.filter(q => q.label || q.url).length > 0 ? (
                quickLinks.map((q, idx) => (
                  <span
                    key={idx}
                    className="inline-flex items-center rounded-full border border-indigo-200 bg-white px-2.5 py-1 text-[11px] text-indigo-700"
                  >
                    {q.label || 'Link'} →
                  </span>
                ))
              ) : (
                <span className="text-xs text-slate-500 italic">
                  Add CALM / HR / safety quick links in the form.
                </span>
              )}
            </div>
          </section>

          <footer className="border-t border-slate-200 pt-3 text-[11px] text-center text-slate-400">
            Internal newsletter for Port City BPO staff. Please keep this information confidential.
          </footer>
        </div>
      </div>
    </div>
  );
}
