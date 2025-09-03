import React, { useMemo, useState } from "react";

// =============================
// Types
// =============================
type Recommendation = keyof typeof REC_META;

// =============================
// Traffic-light colors (tailwind)
// =============================
const REC_META = {
  na: {
    label: "N/A – Not enough information",
    color: "bg-slate-500",
    ring: "ring-slate-600/30",
    icon: "❔",
  },
  green: {
    label: "Go (with FSM)",
    color: "bg-green-500",
    ring: "ring-green-600/30",
    icon: "✅",
  },
  yellow: {
    label: "Maybe",
    color: "bg-yellow-500",
    ring: "ring-yellow-600/30",
    icon: "⚠️",
  },
  red: {
    label: "No (avoid FSM)",
    color: "bg-red-500",
    ring: "ring-red-600/30",
    icon: "⛔️",
  },
} as const;

// =============================
// Heuristic Rules
// =============================
const RULES = {
  multiStates: { label: "3+ steps with business meaning", weight: 2, group: "Process Complexity" },
  guards: { label: "Rules/permissions needed between steps", weight: 2, group: "Safety & Control" },
  sideEffects: { label: "Transitions trigger side-effects (emails/webhooks)", weight: 2, group: "Side-effects" },
  asyncActors: { label: "Async or multi-actor flow", weight: 2, group: "Process Complexity" },
  rollback: { label: "Need rollback/compensation on failure", weight: 3, group: "Safety & Control" },
  simpleToggle: { label: "Just a simple toggle", weight: -2, group: "Simplicity Signals" },
  oneTime: { label: "One-time / one-and-done action", weight: -1, group: "Simplicity Signals" },
  readOnly: { label: "Read-only status display", weight: -2, group: "Simplicity Signals" },
} as const;

// =============================
// Evaluation logic (single source of truth)
// =============================
function evaluate(answers: Record<string, boolean>): {
  contributions: Array<{
    id: string;
    label: string;
    group: string;
    on: boolean;
    weight: number;
    contribution: number;
    sign: string;
  }>;
  score: number;
  recommendation: Recommendation;
} {
  const contributions = Object.entries(RULES).map(([id, r]) => {
    const on = !!answers[id];
    const contribution = on ? r.weight : 0;
    return {
      id,
      label: r.label,
      group: r.group,
      on,
      weight: r.weight,
      contribution,
      sign: r.weight > 0 ? "+" : "–",
    };
  });

  const score = contributions.reduce((s, c) => s + c.contribution, 0);
  const anyAnswered = contributions.some((c) => c.on);
  const positivesOn = contributions.filter((c) => c.on && c.weight > 0).length;
  const negativesOn = contributions.filter((c) => c.on && c.weight < 0).length;

  // Special rule: if exactly one positive signal is selected and no negatives, show Yellow (Maybe)
  const specialSinglePositive = positivesOn === 1 && negativesOn === 0;

  const recommendation: Recommendation = !anyAnswered
    ? "na"
    : specialSinglePositive
    ? "yellow"
    : score >= 6
    ? "green"
    : score >= 3
    ? "yellow"
    : "red";

  return { contributions, score, recommendation };
}

// =============================
// Derive a draft FSM template from answers
// =============================
function deriveTemplate(answers: Record<string, boolean>) {
  const states = ["idle"] as string[];
  if (answers.asyncActors) states.push("processing");
  if (answers.rollback) states.push("failed");
  states.push("done");

  const transitions: Array<{ from: string; to: string; on: string }> = [];
  transitions.push({ from: "idle", to: answers.asyncActors ? "processing" : "done", on: "START" });
  if (answers.asyncActors) {
    transitions.push({ from: "processing", to: "done", on: "SUCCESS" });
    transitions.push({ from: "processing", to: answers.rollback ? "failed" : "done", on: "FAIL" });
  }
  transitions.push({ from: "*", to: "idle", on: "RESET" });

  const effects: Array<{ when: string; action: string }> = [];
  if (answers.sideEffects) {
    effects.push({ when: "on done", action: "Send email" });
    effects.push({ when: "on any transition", action: "Emit webhook" });
    if (answers.asyncActors) effects.push({ when: "on processing", action: "Enqueue background job" });
  }

  const requirePermissions = !!answers.guards;

  return { states, transitions, effects, requirePermissions } as const;
}

// =============================
// UI Components
// =============================
function Section({ title, children, right }: { title: string; children: React.ReactNode; right?: React.ReactNode }) {
  return (
    <div className="bg-slate-900/60 border border-slate-800 rounded-2xl p-5 shadow-sm">
      <div className="flex items-center justify-between mb-3">
        <h2 className="text-slate-100 text-lg font-semibold">{title}</h2>
        {right}
      </div>
      {children}
    </div>
  );
}

function TrafficLight({ recommendation, score }: { recommendation: Recommendation; score: number }) {
  const meta = REC_META[recommendation];
  return (
    <div className="flex items-center gap-4">
      <span className={`inline-flex h-10 w-10 items-center justify-center rounded-full ${meta.color} ring-8 ${meta.ring}`} aria-label={meta.label} />
      <div>
        <div className="text-slate-200 font-medium flex items-center gap-2">
          <span className="text-xl">{meta.icon}</span>
          {meta.label}
        </div>
        {recommendation === "na" ? (
          <div className="text-slate-400 text-sm">Not enough information yet. Check any box to get a recommendation.</div>
        ) : (
          <div className="text-slate-400 text-sm">
            Score: <span className="font-medium text-slate-200">{score}</span> (Green ≥ 6 · Yellow 3–5 · Red ≤ 2)
          </div>
        )}
      </div>
    </div>
  );
}

// Simple accessible tooltip with keyboard focus support
function Tooltip({ content }: { content: React.ReactNode }) {
  return (
    <span className="relative inline-flex items-center group">
      <button
        type="button"
        className="inline-flex h-4 w-4 items-center justify-center rounded-full border border-slate-600 text-[10px] leading-none text-slate-300 bg-slate-800/60 hover:text-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-500"
        aria-label="More info"
        tabIndex={0}
      >
        i
      </button>
      <span
        role="tooltip"
        className="pointer-events-none absolute left-1/2 -translate-x-1/2 top-6 z-20 w-80 max-w-[22rem] rounded-md bg-slate-900 border border-slate-700 text-slate-200 text-xs p-3 shadow-xl opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 transition"
      >
        {content}
      </span>
    </span>
  );
}

const TIPS: Record<string, React.ReactNode> = {
  asyncActors: (
    <div>
      <div className="font-medium text-slate-100 mb-1">Async or multi‑actor</div>
      <ul className="list-disc pl-4 space-y-1 text-slate-300">
        <li>Work continues after the request (queues/cron/background jobs).</li>
        <li>Different people/systems complete steps at different times.</li>
      </ul>
      <div className="mt-2 text-slate-400">Examples: video upload → transcode job; KYC submission → compliance review; order → warehouse pick/pack.</div>
    </div>
  ),
  sideEffects: (
    <div>
      <div className="font-medium text-slate-100 mb-1">Transitions trigger side‑effects</div>
      <div className="text-slate-300">Actions that fire when the state changes.</div>
      <ul className="list-disc pl-4 space-y-1 text-slate-300 mt-1">
        <li>
          on <code>done</code> → send confirmation email
        </li>
        <li>on any transition → emit webhook / write audit log</li>
        <li>
          on <code>processing</code> → enqueue background task
        </li>
        <li>
          on <code>failed</code> → alert/notify
        </li>
      </ul>
    </div>
  ),
  rollback: (
    <div>
      <div className="font-medium text-slate-100 mb-1">Rollback / compensation</div>
      <div className="text-slate-300">
        If a later step fails, you must undo or compensate earlier side‑effects (often across services where a single transaction isn’t possible).
      </div>
      <ul className="list-disc pl-4 space-y-1 text-slate-300 mt-1">
        <li>Refund a charge if the order is canceled after payment</li>
        <li>Release inventory reservation on failure</li>
        <li>Delete uploaded assets if validation fails</li>
        <li>Publish compensating ledger entry</li>
      </ul>
    </div>
  ),
};

// =============================
// Lightweight runtime tests (console only)
// =============================
function runDevTests() {
  // NOTE: Per instruction, do NOT change existing tests unless clearly wrong.
  const tests: Array<{ name: string; answers: Record<string, boolean>; expectScore: number | null; expectRec: Recommendation }> = [
    { name: "NA when no answers", answers: {}, expectScore: 0, expectRec: "na" },
    { name: "Single +2 positive → Yellow", answers: { multiStates: true }, expectScore: 2, expectRec: "yellow" },
    { name: "Green at 6+", answers: { multiStates: true, guards: true, sideEffects: true }, expectScore: 6, expectRec: "green" },
    { name: "Yellow at 3–5", answers: { rollback: true }, expectScore: 3, expectRec: "yellow" },
    { name: "Red at ≤2 (mixed)", answers: { multiStates: true, oneTime: true }, expectScore: 1, expectRec: "red" },
    { name: "Negative strong red", answers: { readOnly: true, simpleToggle: true }, expectScore: -4, expectRec: "red" },
  ];
  let pass = 0;
  tests.forEach((t) => {
    const { score, recommendation } = evaluate(t.answers);
    const scoreOk = t.expectScore === null ? true : score === t.expectScore;
    const recOk = recommendation === t.expectRec;
    if (scoreOk && recOk) {
      pass++;
      // eslint-disable-next-line no-console
      console.log(`✅ [test] ${t.name}: score=${score}, rec=${recommendation}`);
    } else {
      // eslint-disable-next-line no-console
      console.error(`❌ [test] ${t.name}: got score=${score}, rec=${recommendation}; expected score=${t.expectScore}, rec=${t.expectRec}`);
    }
  });
  // eslint-disable-next-line no-console
  console.log(`✓ ${pass}/${tests.length} tests passed`);
}

// Run tests once at module load (harmless console output)
runDevTests();

// =============================
// App
// =============================
export default function App() {
  const [answers, setAnswers] = useState<Record<string, boolean>>(
    () => Object.fromEntries(Object.keys(RULES).map((k) => [k, false])) as Record<string, boolean>
  );
  const [logs, setLogs] = useState<string[]>([]);
  const { contributions, score, recommendation } = useMemo(() => evaluate(answers), [answers]);
  const template = useMemo(() => deriveTemplate(answers), [answers]);

  const groups = useMemo(() => {
    const m: Record<string, Array<{ id: string } & (typeof RULES)[keyof typeof RULES]>> = {};
    Object.entries(RULES).forEach(([id, r]) => {
      if (!m[r.group]) m[r.group] = [];
      m[r.group].push({ id, ...(r as any) });
    });
    return m;
  }, []);

  function log(msg: string) {
    const stamp = new Date().toLocaleTimeString();
    setLogs((prev) => [`${stamp} ${msg}`, ...prev].slice(0, 120));
  }

  function toggle(id: string) {
    setAnswers((prev) => {
      const before = evaluate(prev).score;
      const next = { ...prev, [id]: !prev[id] };
      const after = evaluate(next).score;
      const w = (RULES as any)[id].weight * (next[id] ? 1 : -1);
      log(`${next[id] ? "Enabled" : "Disabled"} “${(RULES as any)[id].label}”: ${w > 0 ? "+" : ""}${w} (${before} → ${after})`);
      return next;
    });
  }

  // Simulator state
  const [simOpen, setSimOpen] = useState(false);
  const [current, setCurrent] = useState("idle");
  const [simLog, setSimLog] = useState<string[]>([]);
  const [permissionsOk, setPermissionsOk] = useState(true);

  function allowedTransitions() {
    return template.transitions.filter((t) => t.from === current || t.from === "*");
  }

  function fire(eventName: string) {
    const options = allowedTransitions().filter((t) => t.on === eventName);
    if (!options.length) {
      log(`SIM: No transition for event ${eventName} from ${current}`);
      setSimLog((prev) => [`No transition for ${eventName} from ${current}`, ...prev]);
      return;
    }
    if (template.requirePermissions && !permissionsOk) {
      log(`SIM: Guard blocked ${eventName} from ${current} (permissions required)`);
      setSimLog((prev) => [`Guard blocked: permissions required`, ...prev]);
      return;
    }
    const t = options[0];
    const to = t.to;
    const from = current;
    setCurrent(to);
    log(`SIM: ${from} —[${eventName}]→ ${to}`);
    const effectLines: string[] = [];
    template.effects.forEach((e) => {
      const any = e.when.includes("any transition");
      const onProcessing = e.when.includes("processing");
      const onDone = e.when.includes("done");
      if (any || (onProcessing && to === "processing") || (onDone && to === "done")) {
        const line = `effect: ${e.action} (${e.when})`;
        log(`SIM: ${line}`);
        effectLines.push(line);
      }
    });
    setSimLog((prev) => [`${from} —[${eventName}]→ ${to}`, ...effectLines, ...prev]);
  }

  function resetSim() {
    setCurrent("idle");
    setSimLog((prev) => ["reset to idle", ...prev]);
  }

  const positives = contributions
    .filter((c) => c.contribution > 0)
    .sort((a, b) => b.contribution - a.contribution);
  const negatives = contributions
    .filter((c) => c.contribution < 0)
    .sort((a, b) => a.contribution - b.contribution);

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 p-6 md:p-10">
      <div className="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div className="lg:col-span-2 space-y-6">
          <Section title="Decision Maker" right={<TrafficLight recommendation={recommendation} score={score} />}>
            <p className="text-slate-400 mb-4">Answer a few questions to see if your flow benefits from an FSM. The panel on the right explains the logic in real time.</p>
            <div className="grid sm:grid-cols-2 gap-5">
              {Object.entries(groups).map(([group, items]) => (
                <div key={group} className="bg-slate-900/40 border border-slate-800 rounded-xl p-4">
                  <div className="text-sm font-medium text-slate-300 mb-2">{group}</div>
                  <div className="space-y-2">
                    {items.map((item: any) => (
                      <label key={item.id} className="flex items-start gap-3 cursor-pointer">
                        <input
                          type="checkbox"
                          className="mt-1 h-4 w-4 rounded border-slate-600 bg-slate-900"
                          checked={!!(answers as any)[item.id]}
                          onChange={() => toggle(item.id)}
                          aria-label={item.label}
                        />
                        <span className="text-slate-200 text-sm leading-5 flex items-start gap-2">
                          {item.label}
                          {TIPS[item.id] && <Tooltip content={TIPS[item.id]} />}
                        </span>
                      </label>
                    ))}
                  </div>
                </div>
              ))}
            </div>

            <div className="mt-5 flex flex-wrap gap-3">
              {recommendation === "na" && (
                <button className="px-4 py-2 rounded-xl bg-slate-800/70 border border-slate-700 text-slate-400 cursor-not-allowed" disabled>
                  Answer any question to get a recommendation
                </button>
              )}
              {recommendation === "green" && (
                <button className="px-4 py-2 rounded-xl bg-green-600/90 hover:bg-green-600 transition">Apply Recommendation: Generate FSM Template</button>
              )}
              {(recommendation === "green" || recommendation === "yellow") && (
                <button onClick={() => setSimOpen(true)} className="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 transition">
                  Open Simulator with Recommended Settings
                </button>
              )}
              {recommendation === "red" && (
                <button className="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 transition">See Simple Flow Template</button>
              )}
            </div>
          </Section>

          <Section
            title="Advanced Mode: FSM Simulator"
            right={
              <button onClick={() => setSimOpen(!simOpen)} className="px-3 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-sm">
                {simOpen ? "Hide" : "Show"}
              </button>
            }
          >
            <p className="text-slate-400 text-sm mb-4">
              The simulator turns your answers into a draft FSM. Trigger events, test guards, and watch side‑effects fire. It uses the same scoring and assumptions as the Decision Maker.
            </p>
            {simOpen && (
              <div className="grid md:grid-cols-3 gap-5">
                <div className="md:col-span-2 space-y-4">
                  <div className="bg-slate-900/40 border border-slate-800 rounded-xl p-4">
                    <div className="flex items-center gap-3 mb-3">
                      <div className="text-sm text-slate-300">Current State:</div>
                      <div className="px-2.5 py-1 rounded-md bg-slate-800 border border-slate-700 text-slate-100 text-sm">{current}</div>
                      <div className="ml-auto text-slate-300 text-sm">Allowed:</div>
                      <div className="flex gap-2 flex-wrap">
                        {allowedTransitions().map((t, i) => (
                          <span key={i} className="px-2 py-0.5 rounded-md bg-slate-800 text-slate-200 text-xs border border-slate-700">
                            {t.on} → {t.to}
                          </span>
                        ))}
                      </div>
                    </div>

                    <div className="flex flex-wrap gap-2">
                      {["START", "SUCCESS", "FAIL", "RESET"].map((ev) => (
                        <button key={ev} onClick={() => fire(ev)} className="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-sm">
                          {ev}
                        </button>
                      ))}
                    </div>
                  </div>

                  <div className="bg-slate-900/40 border border-slate-800 rounded-xl p-4">
                    <div className="text-sm font-medium text-slate-300 mb-2">Guards</div>
                    {template.requirePermissions ? (
                      <label className="flex items-center gap-2 text-sm">
                        <input type="checkbox" className="h-4 w-4" checked={permissionsOk} onChange={() => setPermissionsOk((v) => !v)} />
                        Permissions ok
                      </label>
                    ) : (
                      <div className="text-slate-400 text-sm">No guards required for the current recommendation.</div>
                    )}
                  </div>

                  <div className="bg-slate-900/40 border border-slate-800 rounded-xl p-4">
                    <div className="text-sm font-medium text-slate-300 mb-2">Effects (from your answers)</div>
                    {template.effects.length ? (
                      <ul className="list-disc pl-5 text-sm text-slate-300 space-y-1">
                        {template.effects.map((e, i) => (
                          <li key={i}>
                            <span className="text-slate-400">{e.when}:</span> {e.action}
                          </li>
                        ))}
                      </ul>
                    ) : (
                      <div className="text-slate-400 text-sm">No side-effects configured.</div>
                    )}
                  </div>
                </div>

                <div className="space-y-4">
                  <div className="bg-slate-900/40 border border-slate-800 rounded-xl p-4">
                    <div className="text-sm font-medium text-slate-300 mb-2">States</div>
                    <div className="flex flex-wrap gap-2">
                      {template.states.map((s) => (
                        <span key={s} className={`px-2 py-1 rounded-md border text-xs ${s === current ? "bg-slate-200 text-slate-900 border-slate-300" : "bg-slate-800 border-slate-700 text-slate-200"}`}>
                          {s}
                        </span>
                      ))}
                    </div>
                  </div>

                  <div className="bg-slate-900/40 border border-slate-800 rounded-xl p-4">
                    <div className="flex items-center justify-between mb-2">
                      <div className="text-sm font-medium text-slate-300">Simulator Log</div>
                      <button onClick={resetSim} className="text-xs px-2 py-1 rounded-md bg-slate-800 border border-slate-700">
                        Reset
                      </button>
                    </div>
                    <div className="text-xs text-slate-300 space-y-1 max-h-52 overflow-auto">
                      {simLog.length ? simLog.map((l, i) => <div key={i}>• {l}</div>) : <div className="text-slate-500">(No events yet)</div>}
                    </div>
                  </div>
                </div>
              </div>
            )}
          </Section>
        </div>

        {/* Right column: Behind the Scenes */}
        <div className="space-y-6">
          <Section title="Behind the Scenes" right={<div className="text-slate-400 text-xs">Live</div>}>
            <div className="space-y-4">
              <div className="bg-slate-900/40 border border-slate-800 rounded-xl p-4">
                <div className="text-sm font-medium text-slate-300 mb-2">Why this recommendation</div>
                {recommendation === "na" ? (
                  <div className="text-slate-500 text-sm">Start by checking a few boxes. We’ll light up the recommendation and show how the logic works.</div>
                ) : (
                  <ul className="text-sm text-slate-300 space-y-1">
                    {positives.slice(0, 2).map((r) => (
                      <li key={r.id}>
                        ✔︎ {r.label} <span className="text-slate-400">(+{r.contribution})</span>
                      </li>
                    ))}
                    {negatives.slice(0, 1).map((r) => (
                      <li key={r.id}>
                        ✖︎ {r.label} <span className="text-slate-400">({r.contribution})</span>
                      </li>
                    ))}
                  </ul>
                )}
              </div>

              <div className="bg-slate-900/40 border border-slate-800 rounded-xl p-4">
                <div className="text-sm font-medium text-slate-300 mb-2">Scoring ranges</div>
                <ul className="text-sm text-slate-300 space-y-1 mb-3">
                  <li>Green (Go): score ≥ 6</li>
                  <li>Yellow (Maybe): score 3–5</li>
                  <li>Red (No): score ≤ 2</li>
                  <li className="text-slate-400">Special: if exactly one positive signal is selected and no negatives, we show <span className="font-medium">Maybe</span>.</li>
                </ul>
                <div className="text-sm font-medium text-slate-300 mb-3">Rules fired</div>
                <div className="space-y-2">
                  {contributions.map((r) => (
                    <div key={r.id} className="flex items-center justify-between text-sm">
                      <div className="flex items-center gap-2">
                        <span className={`inline-flex h-2.5 w-2.5 rounded-full ${r.on ? "bg-slate-200" : "bg-slate-700"}`} />
                        <span className="text-slate-300">{r.label}</span>
                      </div>
                      <div className={`tabular-nums ${r.contribution > 0 ? "text-green-400" : r.contribution < 0 ? "text-red-400" : "text-slate-500"}`}>
                        {r.contribution > 0 ? "+" : ""}
                        {r.contribution}
                      </div>
                    </div>
                  ))}
                </div>
              </div>

              <div className="bg-slate-900/40 border border-slate-800 rounded-xl p-4">
                <div className="text-sm font-medium text-slate-300 mb-2">Actions & side‑effects</div>
                {template.effects.length ? (
                  <ul className="text-sm text-slate-300 space-y-1">
                    {template.effects.map((e, i) => (
                      <li key={i}>
                        • {e.action} <span className="text-slate-400">({e.when})</span>
                      </li>
                    ))}
                  </ul>
                ) : (
                  <div className="text-slate-500 text-sm">None configured yet.</div>
                )}
              </div>

              <div className="bg-slate-900/40 border border-slate-800 rounded-xl p-4">
                <div className="text-sm font-medium text-slate-300 mb-2">Debug Log</div>
                <div className="text-xs text-slate-300 space-y-1 max-h-56 overflow-auto">
                  {logs.length ? logs.map((l, i) => <div key={i}>• {l}</div>) : <div className="text-slate-500">(No activity yet)</div>}
                </div>
              </div>
            </div>
          </Section>
        </div>
      </div>
    </div>
  );
}
