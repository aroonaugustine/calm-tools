import React, { useEffect, useState } from 'react';
import RichTextEditor from './components/RichTextEditor';
import ImageCropperModal from './components/ImageCropperModal';
import NewsletterPreview from './components/NewsletterPreview';
import HtmlExportPanel from './components/HtmlExportPanel';
import { defaultContent } from './sampleContent';

const STORAGE_KEY = 'portPulseIssues_v2';

export default function App() {
  const [data, setData] = useState(defaultContent);
  const [savedIssues, setSavedIssues] = useState([]);
  const [selectedIssueId, setSelectedIssueId] = useState('');
  const [htmlOutput, setHtmlOutput] = useState('');
  const [cropState, setCropState] = useState({
    open: false,
    targetField: null,
    src: null,
    aspect: 4 / 3
  });

  useEffect(() => {
    const stored = window.localStorage.getItem(STORAGE_KEY);
    if (stored) {
      try {
        const parsed = JSON.parse(stored);
        setSavedIssues(parsed);
      } catch (e) {
        console.error('Failed to parse stored issues', e);
      }
    }
  }, []);

  const updateField = (field, value) => {
    setData(prev => ({ ...prev, [field]: value }));
  };

  const updateQuickLink = (idx, field, value) => {
    setData(prev => {
      const next = [...prev.quickLinks];
      next[idx] = { ...next[idx], [field]: value };
      return { ...prev, quickLinks: next };
    });
  };

  const openCropperFor = (field, aspect = 4 / 3) => {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = (e) => {
      const file = e.target.files?.[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = (ev) => {
        setCropState({
          open: true,
          targetField: field,
          src: ev.target.result,
          aspect
        });
      };
      reader.readAsDataURL(file);
    };
    input.click();
  };

  const handleCropApply = (dataUrl) => {
    if (cropState.targetField) {
      setData(prev => ({ ...prev, [cropState.targetField]: dataUrl }));
    }
    setCropState({ open: false, targetField: null, src: null, aspect: 4 / 3 });
  };

  const handleCropCancel = () => {
    setCropState({ open: false, targetField: null, src: null, aspect: 4 / 3 });
  };

  const handleSaveIssue = () => {
    const name = data.issueName?.trim() || `Issue ${new Date().toISOString()}`;
    const id = Date.now().toString();
    const newIssue = { id, name, data };
    const updated = [...savedIssues, newIssue];
    setSavedIssues(updated);
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(updated));
    setSelectedIssueId(id);
    alert('Issue saved locally in this browser.');
  };

  const handleLoadIssue = (id) => {
    setSelectedIssueId(id);
    const found = savedIssues.find(i => i.id === id);
    if (found) {
      setData(found.data);
    }
  };

  return (
    <div className="min-h-screen flex flex-col bg-slate-100">
      <header className="border-b border-slate-200 bg-white/80 backdrop-blur sticky top-0 z-10">
        <div className="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between gap-4">
          <div>
            <div className="text-xs uppercase tracking-[0.2em] text-slate-400">
              Port City BPO
            </div>
            <h1 className="text-sm md:text-base font-semibold text-slate-800 flex items-center gap-2">
              Port Pulse — Newsletter Builder
              <span className="text-[10px] font-mono text-slate-400 border border-slate-200 rounded px-1 py-0.5">
                v2.0.0
              </span>
            </h1>
          </div>
          <div className="flex items-center gap-2 text-xs">
            <select
              className="border border-slate-200 rounded-lg px-2 py-1 text-xs bg-white"
              value={selectedIssueId}
              onChange={e => handleLoadIssue(e.target.value)}
            >
              <option value="">Load saved issue...</option>
              {savedIssues.map(issue => (
                <option key={issue.id} value={issue.id}>
                  {issue.name}
                </option>
              ))}
            </select>
            <button
              onClick={handleSaveIssue}
              className="inline-flex items-center rounded-lg bg-slate-900 px-3 py-1 text-xs font-medium text-white hover:bg-slate-800"
            >
              Save Issue
            </button>
          </div>
        </div>
      </header>

      <ImageCropperModal
        open={cropState.open}
        title="Crop image"
        src={cropState.src}
        aspect={cropState.aspect}
        onCancel={handleCropCancel}
        onApply={handleCropApply}
      />

      <main className="flex-1 max-w-7xl mx-auto w-full px-4 py-4 md:py-6 overflow-hidden">
  <div className="grid lg:grid-cols-2 gap-4 md:gap-6 h-[calc(100vh-110px)] overflow-hidden">
          <div className="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 md:p-5 h-full overflow-y-auto">
            <h2 className="text-sm font-semibold text-slate-800 mb-3">
              Newsletter Settings & Content
            </h2>

            <div className="space-y-4 text-xs md:text-sm">
              <section className="space-y-2">
                <div className="grid md:grid-cols-2 gap-3">
                  <div>
                    <label className="block text-xs font-medium text-slate-600 mb-1">
                      Newsletter Name
                    </label>
                    <input
                      type="text"
                      className="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs"
                      value={data.newsletterName}
                      onChange={e => updateField('newsletterName', e.target.value)}
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-medium text-slate-600 mb-1">
                      Edition
                    </label>
                    <input
                      type="text"
                      className="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs"
                      value={data.edition}
                      onChange={e => updateField('edition', e.target.value)}
                    />
                  </div>
                </div>
                <div className="grid md:grid-cols-2 gap-3">
                  <div>
                    <label className="block text-xs font-medium text-slate-600 mb-1">
                      Issue Title (internal)
                    </label>
                    <input
                      type="text"
                      className="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs"
                      value={data.issueTitle}
                      onChange={e => updateField('issueTitle', e.target.value)}
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-medium text-slate-600 mb-1">
                      Issue Name (for saving)
                    </label>
                    <input
                      type="text"
                      className="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs"
                      value={data.issueName}
                      onChange={e => updateField('issueName', e.target.value)}
                    />
                  </div>
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-600 mb-1">
                    Logo URL (optional)
                  </label>
                  <input
                    type="text"
                    className="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs"
                    placeholder="https://..."
                    value={data.logoUrl}
                    onChange={e => updateField('logoUrl', e.target.value)}
                  />
                  <p className="text-[10px] text-slate-400 mt-1">
                    If empty, a placeholder logo is used.
                  </p>
                </div>
              </section>

              <hr className="border-slate-100" />

              <section className="space-y-2">
                <h3 className="text-xs font-semibold text-slate-700">
                  Background Image
                </h3>
                <div className="flex flex-wrap items-center gap-2">
                  <button
                    type="button"
                    onClick={() => openCropperFor('backgroundImage', 16 / 9)}
                    className="text-xs px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-700"
                  >
                    {data.backgroundImage ? 'Change Background' : 'Upload Background'}
                  </button>
                  {data.backgroundImage && (
                    <button
                      type="button"
                      onClick={() => updateField('backgroundImage', '')}
                      className="text-xs px-3 py-1.5 rounded-lg border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700"
                    >
                      Remove
                    </button>
                  )}
                </div>
                {data.backgroundImage && (
                  <div className="space-y-2">
                    <div className="flex items-center gap-3">
                      <label className="text-[11px] font-medium text-slate-600">
                        Opacity
                      </label>
                      <input
                        type="range"
                        min={0}
                        max={0.6}
                        step={0.05}
                        value={data.backgroundOpacity ?? 0.15}
                        onChange={e =>
                          updateField('backgroundOpacity', parseFloat(e.target.value))
                        }
                      />
                      <span className="text-[11px] text-slate-500">
                        {(data.backgroundOpacity ?? 0.15).toFixed(2)}
                      </span>
                    </div>
                    <div className="flex flex-wrap gap-2 text-[11px]">
                      {['cover', 'contain', 'repeat', 'auto'].map(mode => (
                        <button
                          key={mode}
                          type="button"
                          onClick={() => updateField('backgroundMode', mode)}
                          className={`px-2 py-1 rounded-full border ${
                            data.backgroundMode === mode
                              ? 'border-slate-900 bg-slate-900 text-white'
                              : 'border-slate-200 bg-white text-slate-700'
                          }`}
                        >
                          {mode}
                        </button>
                      ))}
                    </div>
                  </div>
                )}
              </section>

              <hr className="border-slate-100" />

              <section className="space-y-2">
                <h3 className="text-xs font-semibold text-slate-700">
                  1. Message from Leadership
                </h3>
                <div className="flex items-center gap-2">
                  <button
                    type="button"
                    onClick={() => openCropperFor('leadershipImage', 1)}
                    className="text-[11px] px-2.5 py-1 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-700"
                  >
                    {data.leadershipImage ? 'Change Photo' : 'Upload Photo'}
                  </button>
                  {data.leadershipImage && (
                    <button
                      type="button"
                      onClick={() => updateField('leadershipImage', '')}
                      className="text-[11px] px-2.5 py-1 rounded-lg border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700"
                    >
                      Remove
                    </button>
                  )}
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-600 mb-1">
                    Section Title
                  </label>
                  <input
                    type="text"
                    className="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs"
                    value={data.leadershipTitle}
                    onChange={e => updateField('leadershipTitle', e.target.value)}
                  />
                </div>
                <RichTextEditor
                  label="Leadership Message"
                  value={data.leadershipBodyHtml}
                  onChange={val => updateField('leadershipBodyHtml', val)}
                  helperText="Formatted as a friendly note from Gary. Sign-off is added automatically."
                />
              </section>

              <section className="space-y-2">
                <h3 className="text-xs font-semibold text-slate-700">
                  2. Important Announcements
                </h3>
                <RichTextEditor
                  value={data.importantAnnouncementsHtml}
                  onChange={val => updateField('importantAnnouncementsHtml', val)}
                  helperText="Use bullets for each announcement."
                />
              </section>

              <section className="space-y-2">
                <h3 className="text-xs font-semibold text-slate-700">
                  3. Compliance Reminders
                </h3>
                <div className="flex items-center gap-2">
                  <button
                    type="button"
                    onClick={() => openCropperFor('complianceImage', 16 / 9)}
                    className="text-[11px] px-2.5 py-1 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-700"
                  >
                    {data.complianceImage ? 'Change Image' : 'Upload Image'}
                  </button>
                  {data.complianceImage && (
                    <button
                      type="button"
                      onClick={() => updateField('complianceImage', '')}
                      className="text-[11px] px-2.5 py-1 rounded-lg border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700"
                    >
                      Remove
                    </button>
                  )}
                </div>
                <RichTextEditor
                  value={data.complianceRemindersHtml}
                  onChange={val => updateField('complianceRemindersHtml', val)}
                  helperText="E.g. no vapes, no foreign cigarettes, no importing these items."
                />
              </section>

              <section className="space-y-2">
                <h3 className="text-xs font-semibold text-slate-700">
                  4. Safety Reminders (Storms / Floods)
                </h3>
                <RichTextEditor
                  value={data.safetyRemindersHtml}
                  onChange={val => updateField('safetyRemindersHtml', val)}
                  helperText="Use short bullets, practical and actionable."
                />
              </section>

              <section className="space-y-2">
                <h3 className="text-xs font-semibold text-slate-700">
                  5. Special Message (Xmas Season etc.)
                </h3>
                <RichTextEditor
                  value={data.specialMessageHtml}
                  onChange={val => updateField('specialMessageHtml', val)}
                  helperText="Heartfelt, seasonal message."
                />
              </section>

              <section className="space-y-2">
                <h3 className="text-xs font-semibold text-slate-700">
                  6. Useful Information (Company & Contacts)
                </h3>
                <RichTextEditor
                  value={data.usefulInfoHtml}
                  onChange={val => updateField('usefulInfoHtml', val)}
                  helperText="Company details and key contacts."
                />
              </section>

              <section className="space-y-2">
                <h3 className="text-xs font-semibold text-slate-700">
                  7. Wins &amp; Shout-outs
                </h3>
                <RichTextEditor
                  value={data.winsAndShoutoutsHtml}
                  onChange={val => updateField('winsAndShoutoutsHtml', val)}
                  helperText="Highlight people and team achievements."
                />
              </section>

              <section className="space-y-2">
                <h3 className="text-xs font-semibold text-slate-700">
                  8. Our CSR Activities
                </h3>
                <div className="flex flex-wrap gap-2 text-[11px] mb-1">
                  <button
                    type="button"
                    onClick={() => openCropperFor('csrImage1', 4 / 3)}
                    className="px-2.5 py-1 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-700"
                  >
                    {data.csrImage1 ? 'Change CSR Image 1' : 'Upload CSR Image 1'}
                  </button>
                  {data.csrImage1 && (
                    <button
                      type="button"
                      onClick={() => updateField('csrImage1', '')}
                      className="px-2.5 py-1 rounded-lg border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700"
                    >
                      Remove 1
                    </button>
                  )}
                  <button
                    type="button"
                    onClick={() => openCropperFor('csrImage2', 4 / 3)}
                    className="px-2.5 py-1 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-700"
                  >
                    {data.csrImage2 ? 'Change CSR Image 2' : 'Upload CSR Image 2'}
                  </button>
                  {data.csrImage2 && (
                    <button
                      type="button"
                      onClick={() => updateField('csrImage2', '')}
                      className="px-2.5 py-1 rounded-lg border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700"
                    >
                      Remove 2
                    </button>
                  )}
                </div>
                <RichTextEditor
                  value={data.csrHtml}
                  onChange={val => updateField('csrHtml', val)}
                  helperText="Describe recent CSR activities."
                />
              </section>

              <section className="space-y-2">
                <h3 className="text-xs font-semibold text-slate-700">
                  9. Employee Events
                </h3>
                <div className="flex flex-wrap gap-2 text-[11px] mb-1">
                  <button
                    type="button"
                    onClick={() => openCropperFor('eventsImage1', 4 / 3)}
                    className="px-2.5 py-1 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-700"
                  >
                    {data.eventsImage1 ? 'Change Event Image 1' : 'Upload Event Image 1'}
                  </button>
                  {data.eventsImage1 && (
                    <button
                      type="button"
                      onClick={() => updateField('eventsImage1', '')}
                      className="px-2.5 py-1 rounded-lg border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700"
                    >
                      Remove 1
                    </button>
                  )}
                  <button
                    type="button"
                    onClick={() => openCropperFor('eventsImage2', 4 / 3)}
                    className="px-2.5 py-1 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-700"
                  >
                    {data.eventsImage2 ? 'Change Event Image 2' : 'Upload Event Image 2'}
                  </button>
                  {data.eventsImage2 && (
                    <button
                      type="button"
                      onClick={() => updateField('eventsImage2', '')}
                      className="px-2.5 py-1 rounded-lg border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700"
                    >
                      Remove 2
                    </button>
                  )}
                </div>
                <RichTextEditor
                  value={data.employeeEventsHtml}
                  onChange={val => updateField('employeeEventsHtml', val)}
                  helperText="Describe recent internal events."
                />
              </section>

              <section className="space-y-2">
                <h3 className="text-xs font-semibold text-slate-700">
                  10. Quick Links
                </h3>
                {[0, 1, 2].map(idx => (
                  <div
                    key={idx}
                    className="grid md:grid-cols-2 gap-2 mb-1 bg-slate-50 rounded-lg p-2"
                  >
                    <div>
                      <label className="block text-[11px] font-medium text-slate-600 mb-0.5">
                        Link {idx + 1} Label
                      </label>
                      <input
                        type="text"
                        className="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-[11px]"
                        value={data.quickLinks[idx]?.label || ''}
                        onChange={e =>
                          updateQuickLink(idx, 'label', e.target.value)
                        }
                      />
                    </div>
                    <div>
                      <label className="block text-[11px] font-medium text-slate-600 mb-0.5">
                        Link {idx + 1} URL
                      </label>
                      <input
                        type="text"
                        className="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-[11px]"
                        value={data.quickLinks[idx]?.url || ''}
                        onChange={e =>
                          updateQuickLink(idx, 'url', e.target.value)
                        }
                      />
                    </div>
                  </div>
                ))}
              </section>
            </div>
          </div>

          <div className="space-y-4 h-full overflow-y-auto pr-2">
            <NewsletterPreview data={data} />
            <HtmlExportPanel
              data={data}
              htmlOutput={htmlOutput}
              setHtmlOutput={setHtmlOutput}
            />
          </div>
        </div>
      </main>
    </div>
  );
}
