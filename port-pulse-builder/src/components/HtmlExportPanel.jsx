import React from 'react';

function escapeAttr(str = '') {
  return str
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

function generateEmailHtml(data) {
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

  const bgStyle = backgroundImage
    ? `
      background-image: url('${backgroundImage}');
      background-repeat: ${backgroundMode === 'repeat' ? 'repeat' : 'no-repeat'};
      background-size: ${backgroundMode === 'cover' ? 'cover' : backgroundMode === 'contain' ? 'contain' : 'auto'};
      background-position: center;
    `
    : '';

  const bgOverlayOpacity = backgroundOpacity ?? 0.15;

  const quickLinksHtml =
    quickLinks && quickLinks.length
      ? quickLinks
          .filter(q => q.label || q.url)
          .map(
            q => `
          <tr>
            <td style="padding:4px 0;">
              <a href="${escapeAttr(q.url || '#')}" style="color:#2563eb;text-decoration:none;">${escapeAttr(q.label || 'Link')}</a>
            </td>
          </tr>`
          )
          .join('')
      : '';

  const img = (src, alt = '', extraStyles = '') =>
    src
      ? `<img src="${src}" alt="${escapeAttr(alt)}" style="display:block;border-radius:12px;border:1px solid #e5e7eb;${extraStyles}" />`
      : '';

  return `
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8" />
  <title>${escapeAttr(newsletterName)} - ${escapeAttr(edition)}</title>
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;">
  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
    <tr>
      <td align="center" style="padding:24px 8px;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:700px;border-radius:12px;overflow:hidden;position:relative;">
          ${
            backgroundImage
              ? `
          <tr>
            <td style="${bgStyle}position:absolute;inset:0;opacity:${bgOverlayOpacity};filter:brightness(1);"></td>
          </tr>`
              : ''
          }
          <tr>
            <td style="position:relative;background:#ffffff;background-color:rgba(255,255,255,0.9);backdrop-filter:blur(2px);">
              <!-- Header -->
              <table role="presentation" width="100%" style="background:linear-gradient(90deg,#0f172a,#1d4ed8);">
                <tr>
                  <td style="padding:16px 24px;color:#e5e7eb;font-family:system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;">
                    <table role="presentation" width="100%">
                      <tr>
                        <td style="vertical-align:middle;">
                          <img src="${logoSrc}" alt="Port City BPO" style="display:block;max-height:60px;border-radius:4px;" />
                        </td>
                        <td style="text-align:right;vertical-align:middle;">
                          <div style="font-size:11px;letter-spacing:0.12em;text-transform:uppercase;opacity:0.8;">${escapeAttr(
                            newsletterName
                          )}</div>
                          <div style="font-size:18px;font-weight:600;">${escapeAttr(
                            issueTitle || ''
                          )}</div>
                          <div style="font-size:12px;opacity:0.8;">Edition: ${escapeAttr(edition)}</div>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>

              <table role="presentation" width="100%" style="font-family:system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-size:14px;color:#111827;line-height:1.6;padding:24px;">
                <tr>
                  <td>
                    <!-- Leadership -->
                    <table role="presentation" width="100%" style="margin-bottom:24px;border-radius:12px;background:#eff6ff;border:1px solid #dbeafe;">
                      <tr>
                        <td style="padding:16px;">
                          <table role="presentation" width="100%">
                            <tr>
                              <td style="vertical-align:top;">
                                <div style="font-size:11px;font-weight:600;letter-spacing:0.16em;text-transform:uppercase;color:#1d4ed8;margin-bottom:4px;">Message from Leadership</div>
                                <div style="font-size:16px;font-weight:600;margin-bottom:6px;">${escapeAttr(
                                  leadershipTitle
                                )}</div>
                                <div style="font-size:14px;color:#111827;line-height:1.6;">
                                  ${leadershipBodyHtml}
                                </div>
                                <div style="margin-top:12px;font-size:13px;color:#374151;">
                                  Warm regards,<br/>
                                  Gary Seaton<br/>
                                  CEO, Port City BPO (Pvt) Ltd.
                                </div>
                              </td>
                              ${
                                leadershipImage
                                  ? `<td style="width:140px;padding-left:12px;vertical-align:top;">${img(
                                      leadershipImage,
                                      'CEO',
                                      'width:140px;height:140px;object-fit:cover;'
                                    )}</td>`
                                  : ''
                              }
                            </tr>
                          </table>
                        </td>
                      </tr>
                    </table>

                    <!-- Announcements + Compliance -->
                    <table role="presentation" width="100%" style="margin-bottom:24px;" cellpadding="0" cellspacing="0">
                      <tr>
                        <td width="50%" style="padding-right:8px;vertical-align:top;">
                          <table role="presentation" width="100%" style="border-radius:12px;border:1px solid #e5e7eb;background:#ffffff;">
                            <tr>
                              <td style="padding:12px 14px;">
                                <div style="font-size:11px;font-weight:600;letter-spacing:0.16em;text-transform:uppercase;color:#6b21a8;margin-bottom:4px;">Important Announcements</div>
                                <div style="font-size:14px;color:#111827;line-height:1.6;">
                                  ${importantAnnouncementsHtml}
                                </div>
                              </td>
                            </tr>
                          </table>
                        </td>
                        <td width="50%" style="padding-left:8px;vertical-align:top;">
                          <table role="presentation" width="100%" style="border-radius:12px;border:1px solid #fecaca;background:#fef2f2;">
                            <tr>
                              <td style="padding:12px 14px;">
                                <div style="font-size:11px;font-weight:600;letter-spacing:0.16em;text-transform:uppercase;color:#b91c1c;margin-bottom:4px;">Compliance Reminders</div>
                                <div style="font-size:14px;color:#111827;line-height:1.6;">
                                  ${complianceRemindersHtml}
                                </div>
                                ${
                                  complianceImage
                                    ? `<div style="margin-top:8px;">${img(
                                        complianceImage,
                                        'Compliance',
                                        'width:100%;height:90px;object-fit:cover;'
                                      )}</div>`
                                    : ''
                                }
                              </td>
                            </tr>
                          </table>
                        </td>
                      </tr>
                    </table>

                    <!-- Safety -->
                    <table role="presentation" width="100%" style="margin-bottom:24px;border-radius:12px;border:1px solid #bbf7d0;background:#ecfdf5;">
                      <tr>
                        <td style="padding:16px;">
                          <div style="font-size:11px;font-weight:600;letter-spacing:0.16em;text-transform:uppercase;color:#15803d;margin-bottom:4px;">Safety Reminders · Storms &amp; Floods</div>
                          <div style="font-size:14px;color:#111827;line-height:1.6;">
                            ${safetyRemindersHtml}
                          </div>
                        </td>
                      </tr>
                    </table>

                    <!-- Special Message -->
                    <table role="presentation" width="100%" style="margin-bottom:24px;border-radius:12px;border:1px solid #fed7aa;background:#fff7ed;">
                      <tr>
                        <td style="padding:16px;">
                          <div style="font-size:11px;font-weight:600;letter-spacing:0.16em;text-transform:uppercase;color:#c2410c;margin-bottom:4px;">Special Message · Xmas Season</div>
                          <div style="font-size:14px;color:#111827;line-height:1.6;">
                            ${specialMessageHtml}
                          </div>
                        </td>
                      </tr>
                    </table>

                    <!-- CSR -->
                    <table role="presentation" width="100%" style="margin-bottom:24px;border-radius:12px;border:1px solid #e5e7eb;background:#ffffff;">
                      <tr>
                        <td style="padding:16px;">
                          <div style="font-size:11px;font-weight:600;letter-spacing:0.16em;text-transform:uppercase;color:#4b5563;margin-bottom:4px;">Our CSR Activities</div>
                          <table role="presentation" width="100%">
                            <tr>
                              <td style="vertical-align:top;">
                                <div style="font-size:14px;color:#111827;line-height:1.6;">
                                  ${csrHtml}
                                </div>
                              </td>
                              <td style="width:160px;padding-left:12px;vertical-align:top;">
                                ${csrImage1 ? img(csrImage1, 'CSR 1', 'width:100%;height:80px;object-fit:cover;margin-bottom:8px;') : ''}
                                ${csrImage2 ? img(csrImage2, 'CSR 2', 'width:100%;height:80px;object-fit:cover;') : ''}
                              </td>
                            </tr>
                          </table>
                        </td>
                      </tr>
                    </table>

                    <!-- Employee Events -->
                    <table role="presentation" width="100%" style="margin-bottom:24px;border-radius:12px;border:1px solid #e5e7eb;background:#ffffff;">
                      <tr>
                        <td style="padding:16px;">
                          <div style="font-size:11px;font-weight:600;letter-spacing:0.16em;text-transform:uppercase;color:#4b5563;margin-bottom:4px;">Employee Events</div>
                          <table role="presentation" width="100%">
                            <tr>
                              <td style="vertical-align:top;">
                                <div style="font-size:14px;color:#111827;line-height:1.6;">
                                  ${employeeEventsHtml}
                                </div>
                              </td>
                              <td style="width:160px;padding-left:12px;vertical-align:top;">
                                ${eventsImage1 ? img(eventsImage1, 'Event 1', 'width:100%;height:80px;object-fit:cover;margin-bottom:8px;') : ''}
                                ${eventsImage2 ? img(eventsImage2, 'Event 2', 'width:100%;height:80px;object-fit:cover;') : ''}
                              </td>
                            </tr>
                          </table>
                        </td>
                      </tr>
                    </table>

                    <!-- Wins + Useful -->
                    <table role="presentation" width="100%" style="margin-bottom:24px;" cellpadding="0" cellspacing="0">
                      <tr>
                        <td width="50%" style="padding-right:8px;vertical-align:top;">
                          <table role="presentation" width="100%" style="border-radius:12px;border:1px solid #e5e7eb;background:#f9fafb;">
                            <tr>
                              <td style="padding:12px 14px;">
                                <div style="font-size:11px;font-weight:600;letter-spacing:0.16em;text-transform:uppercase;color:#4b5563;margin-bottom:4px;">Wins &amp; Shout-outs</div>
                                <div style="font-size:14px;color:#111827;line-height:1.6;">
                                  ${winsAndShoutoutsHtml}
                                </div>
                              </td>
                            </tr>
                          </table>
                        </td>
                        <td width="50%" style="padding-left:8px;vertical-align:top;">
                          <table role="presentation" width="100%" style="border-radius:12px;border:1px solid #e5e7eb;background:#f9fafb;">
                            <tr>
                              <td style="padding:12px 14px;">
                                <div style="font-size:11px;font-weight:600;letter-spacing:0.16em;text-transform:uppercase;color:#4b5563;margin-bottom:4px;">Useful Information</div>
                                <div style="font-size:14px;color:#111827;line-height:1.6;">
                                  ${usefulInfoHtml}
                                </div>
                              </td>
                            </tr>
                          </table>
                        </td>
                      </tr>
                    </table>

                    <!-- Quick Links -->
                    <table role="presentation" width="100%" style="margin-bottom:8px;border-radius:12px;border:1px solid #bfdbfe;background:#eff6ff;">
                      <tr>
                        <td style="padding:12px 14px;font-size:13px;">
                          <div style="font-size:11px;font-weight:600;letter-spacing:0.16em;text-transform:uppercase;color:#1d4ed8;margin-bottom:4px;">Quick Links</div>
                          <table role="presentation" width="100%">
                            ${quickLinksHtml}
                          </table>
                        </td>
                      </tr>
                    </table>

                    <!-- Footer -->
                    <table role="presentation" width="100%" style="margin-top:16px;">
                      <tr>
                        <td style="text-align:center;font-size:11px;color:#9ca3af;padding-top:4px;border-top:1px solid #e5e7eb;">
                          You&apos;re receiving Port Pulse as a member of Port City BPO.<br/>
                          Please keep this information internal and confidential.
                        </td>
                      </tr>
                    </table>

                  </td>
                </tr>
              </table>

            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
  `.trim();
}

export default function HtmlExportPanel({ data, htmlOutput, setHtmlOutput }) {
  const handleGenerate = () => {
    const html = generateEmailHtml(data);
    setHtmlOutput(html);
  };

  const handleCopy = async () => {
    try {
      await navigator.clipboard.writeText(htmlOutput);
      alert('HTML copied to clipboard.');
    } catch {
      alert('Could not copy automatically. Please copy manually.');
    }
  };

  return (
    <div className="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 mt-4">
      <div className="flex items-center justify-between mb-2">
        <h2 className="text-sm font-semibold text-slate-800">
          Exported HTML (for Brevo)
        </h2>
        <div className="flex gap-2">
          <button
            onClick={handleGenerate}
            className="inline-flex items-center rounded-lg bg-slate-900 px-3 py-1 text-xs font-medium text-white hover:bg-slate-800"
          >
            Generate HTML
          </button>
          <button
            onClick={handleCopy}
            className="inline-flex items-center rounded-lg border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100"
          >
            Copy HTML
          </button>
        </div>
      </div>
      <p className="text-[11px] text-slate-500 mb-2">
        Paste this into Brevo as a custom HTML email. Embedded images use data URLs; for production you can swap to hosted image URLs if preferred.
      </p>
      <textarea
        rows={14}
        className="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-[11px] font-mono"
        value={htmlOutput}
        onChange={(e) => setHtmlOutput(e.target.value)}
      />
    </div>
  );
}
