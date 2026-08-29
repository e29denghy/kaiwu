"use client";

import { useState } from "react";

type DemoStep = 0 | 1 | 2 | 3;

const demoStates = [
  {
    label: "待批准",
    labelEn: "Awaiting approval",
    note: "Quest 已保存，但尚未获得机器写入权限。",
    event: "quest.created",
    tone: "amber",
  },
  {
    label: "已批准",
    labelEn: "Human approved",
    note: "批准证据已经写入，允许进入目标 Harness 的 Outbox。",
    event: "quest.approved",
    tone: "green",
  },
  {
    label: "执行中",
    labelEn: "Harness running",
    note: "Harness 独立执行，开物只记录状态和审计事件。",
    event: "execution.started",
    tone: "blue",
  },
  {
    label: "等待确认",
    labelEn: "Result review",
    note: "结果已返回；人工确认后才会把 Quest 标记为完成。",
    event: "execution.completed",
    tone: "violet",
  },
] as const;

const principles = [
  ["本地优先", "Local-first"],
  ["人工批准", "Human-approved"],
  ["Harness 中立", "Harness-neutral"],
  ["追加式审计", "Append-only audit"],
];

export default function Home() {
  const [demoStep, setDemoStep] = useState<DemoStep>(0);
  const currentState = demoStates[demoStep];

  return (
    <main>
      <header className="site-header">
        <a className="brand" href="#top" aria-label="KAIWU home">
          <span className="brand-mark" aria-hidden="true">
            开
          </span>
          <span>
            <strong>开物</strong>
            <small>KAIWU</small>
          </span>
        </a>
        <nav aria-label="Main navigation">
          <a href="#workflow">工作流</a>
          <a href="#protocol">协议</a>
          <a href="#roadmap">路线图</a>
        </nav>
        <a
          className="nav-cta"
          href="https://github.com/e29denghy/kaiwu"
          target="_blank"
          rel="noreferrer"
        >
          GitHub <span aria-hidden="true">↗</span>
        </a>
      </header>

      <section className="hero" id="top">
        <div className="hero-copy">
          <div className="eyebrow">
            <span className="pulse-dot" aria-hidden="true" />
            Open source · v0.1.1
          </div>
          <h1>
            <span className="headline-line">
              让 <span lang="en">Agent</span> 执行，
            </span>
            <span className="headline-line">
              把 <strong>决定权</strong> 留给人。
            </span>
          </h1>
          <p className="hero-en">
            Let agents execute. Keep humans in control.
          </p>
          <p className="hero-description">
            开物是一个本地优先、人工批准、跨 Agent Harness 的工作台。它把项目、Quest、审批和审计留在同一条可追溯的时间线上。
          </p>
          <div className="hero-actions">
            <a
              className="button primary"
              href="https://github.com/e29denghy/kaiwu"
              target="_blank"
              rel="noreferrer"
            >
              查看源代码 <span aria-hidden="true">↗</span>
            </a>
            <a className="button secondary" href="#demo">
              体验审批流程 <span aria-hidden="true">↓</span>
            </a>
          </div>
          <p className="install-line">
            <span>$</span> git clone https://github.com/e29denghy/kaiwu.git
          </p>
        </div>

        <div className="hero-console" aria-label="KAIWU workbench preview">
          <div className="console-topbar">
            <span className="console-wordmark">KAIWU / 工作台</span>
            <span className="local-badge">● LOCAL</span>
          </div>
          <div className="console-grid">
            <aside className="console-sidebar" aria-label="Preview navigation">
              <span className="active">今日聚焦</span>
              <span>项目</span>
              <span>Quests</span>
              <span>Harnesses</span>
              <span>审计事件</span>
            </aside>
            <div className="console-content">
              <div className="console-heading">
                <div>
                  <small>MON / 10 AUG</small>
                  <h2>今日聚焦</h2>
                </div>
                <span>2 / 3</span>
              </div>
              <article className="quest-card featured">
                <div className="quest-meta">
                  <span>QUEST #12</span>
                  <span className="risk-low">LOW RISK</span>
                </div>
                <h3>验证 Adapter 协议</h3>
                <p>发布机器可校验的 Event 与 Quest 合同。</p>
                <div className="quest-progress">
                  <span className="done">计划</span>
                  <span className="done">批准</span>
                  <span className="current">执行</span>
                  <span>确认</span>
                </div>
              </article>
              <article className="quest-card muted">
                <div>
                  <small>QUEST #13</small>
                  <h3>Codex Bridge 参考实现</h3>
                </div>
                <span className="draft-badge">DRAFT</span>
              </article>
              <div className="audit-line">
                <span className="audit-icon">✓</span>
                <div>
                  <strong>human.approved</strong>
                  <small>10:24 · append-only evidence</small>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section className="principles" aria-label="KAIWU principles">
        {principles.map(([cn, en], index) => (
          <div key={cn}>
            <span>0{index + 1}</span>
            <strong>{cn}</strong>
            <small>{en}</small>
          </div>
        ))}
      </section>

      <section className="section workflow-section" id="workflow">
        <div className="section-heading">
          <p className="kicker">CONTROLLED WORKFLOW / 受控工作流</p>
          <h2>规划可以来自 AI，授权必须来自人。</h2>
          <p>
            KAIWU 不把 Web 请求变成任意 Shell 权限。它负责协调与留痕，真正的机器执行始终留在目标 Harness 的权限模型里。
          </p>
        </div>
        <div className="workflow-line">
          <article>
            <span className="step-number">01</span>
            <div className="step-icon">Q</div>
            <h3>创建 Quest</h3>
            <small>PLAN / 计划</small>
            <p>记录目标、约束、风险、验收标准和验证方式。</p>
          </article>
          <div className="connector" aria-hidden="true">→</div>
          <article>
            <span className="step-number">02</span>
            <div className="step-icon approval">✓</div>
            <h3>人工批准</h3>
            <small>APPROVE / 授权</small>
            <p>写入型 Quest 未经批准，绝不会进入 Outbox。</p>
          </article>
          <div className="connector" aria-hidden="true">→</div>
          <article>
            <span className="step-number">03</span>
            <div className="step-icon execute">›_</div>
            <h3>Harness 执行</h3>
            <small>EXECUTE / 执行</small>
            <p>Codex、DSH 或其他 Harness 消费同一份版本化协议。</p>
          </article>
          <div className="connector" aria-hidden="true">→</div>
          <article>
            <span className="step-number">04</span>
            <div className="step-icon review">◎</div>
            <h3>结果确认</h3>
            <small>REVIEW / 确认</small>
            <p>事件回写审计时间线，重试也不会覆盖旧记录。</p>
          </article>
        </div>
      </section>

      <section className="demo-section" id="demo">
        <div className="demo-copy">
          <p className="kicker">READ-ONLY DEMO / 只读演示</p>
          <h2>亲手走一遍批准流程。</h2>
          <p>
            这个演示只改变浏览器里的展示状态，不会连接 Harness、修改仓库或执行任何命令。
          </p>
          <div className="demo-controls" role="group" aria-label="Quest workflow demo">
            {demoStates.map((state, index) => (
              <button
                type="button"
                key={state.event}
                className={demoStep === index ? "active" : ""}
                onClick={() => setDemoStep(index as DemoStep)}
                aria-pressed={demoStep === index}
              >
                <span>0{index + 1}</span>
                {state.label}
              </button>
            ))}
          </div>
        </div>
        <div className="demo-panel">
          <div className="demo-panel-top">
            <span>QUEST / 12</span>
            <span className={`state-pill ${currentState.tone}`}>
              {currentState.labelEn}
            </span>
          </div>
          <h3>Publish adapter conformance contract</h3>
          <dl>
            <div>
              <dt>RISK</dt>
              <dd>low</dd>
            </div>
            <div>
              <dt>WRITE</dt>
              <dd>yes</dd>
            </div>
            <div>
              <dt>APPROVAL</dt>
              <dd>{demoStep === 0 ? "pending" : "approved"}</dd>
            </div>
          </dl>
          <div className="demo-status">
            <span className={`status-orb ${currentState.tone}`} aria-hidden="true" />
            <div>
              <small>CURRENT EVENT</small>
              <strong>{currentState.event}</strong>
              <p>{currentState.note}</p>
            </div>
          </div>
          <div className="timeline" aria-label="Quest progress">
            {demoStates.map((state, index) => (
              <span key={state.event} className={index <= demoStep ? "filled" : ""} />
            ))}
          </div>
        </div>
      </section>

      <section className="section protocol-section" id="protocol">
        <div className="protocol-intro">
          <p className="kicker">OPEN CONTRACT / 开放协议</p>
          <h2>换 Harness，不必重写工作台。</h2>
          <p>
            两份公开 JSON Schema 构成稳定边界。Adapter 只负责翻译，不得绕过审批。
          </p>
          <a
            href="https://github.com/e29denghy/kaiwu/tree/main/schemas"
            target="_blank"
            rel="noreferrer"
          >
            查看 Schema <span aria-hidden="true">↗</span>
          </a>
        </div>
        <div className="protocol-cards">
          <article className="protocol-card event-card">
            <div className="protocol-card-head">
              <span>INBOX</span>
              <strong>kaiwu.event/v1</strong>
            </div>
            <pre>{`{
  "schema": "kaiwu.event/v1",
  "type": "execution.completed",
  "status": "completed",
  "occurred_at": "2026-08-10T10:05:00Z"
}`}</pre>
            <p>Harness → KAIWU</p>
          </article>
          <article className="protocol-card quest-protocol-card">
            <div className="protocol-card-head">
              <span>OUTBOX</span>
              <strong>kaiwu.quest/v1</strong>
            </div>
            <pre>{`{
  "schema": "kaiwu.quest/v1",
  "risk_level": "low",
  "requires_write": true,
  "approval": { "status": "approved" }
}`}</pre>
            <p>KAIWU → Harness</p>
          </article>
        </div>
      </section>

      <section className="dsh-section">
        <div className="dsh-label">DSH</div>
        <div>
          <p className="kicker">SDK PREVIEW ADAPTER / 预览适配</p>
          <h2>已经可运行，仍然必须固定版本。</h2>
          <p>
            开物已接入固定 rc6 的官方 DeepSeek Harness SDK / headless CLI：已批准 Quest 通过独立 bridge 进入 DSH，结果回写为 Event。DSH 仍处于 Developer Preview，因此这是可测试的预览兼容，不是稳定性承诺。
          </p>
        </div>
        <span className="future-badge">RC6 PINNED</span>
      </section>

      <section className="section roadmap-section" id="roadmap">
        <div className="section-heading compact">
          <p className="kicker">ROADMAP / 路线图</p>
          <h2>从可靠边界，走向完整执行闭环。</h2>
        </div>
        <ol className="roadmap-list">
          <li className="complete">
            <span>01</span>
            <div>
              <strong>协议一致性验证</strong>
              <small>Schema · fixtures · read-only validator</small>
            </div>
            <b>DONE</b>
          </li>
          <li className="complete">
            <span>02</span>
            <div>
              <strong>DSH Preview Adapter</strong>
              <small>Official SDK / CLI 0.1.0-rc.6</small>
            </div>
            <b>DONE</b>
          </li>
          <li>
            <span>03</span>
            <div>
              <strong>结果确认与差异审查</strong>
              <small>Human review before completion</small>
            </div>
            <b>NEXT</b>
          </li>
          <li>
            <span>04</span>
            <div>
              <strong>Codex Bridge 参考实现</strong>
              <small>Outbox → Codex → Inbox</small>
            </div>
            <b>PLANNED</b>
          </li>
        </ol>
      </section>

      <section className="final-cta">
        <p>OPEN SOURCE · APACHE 2.0</p>
        <h2>把 Agent 交给 Harness，把控制权留给自己。</h2>
        <div>
          <a
            className="button light"
            href="https://github.com/e29denghy/kaiwu"
            target="_blank"
            rel="noreferrer"
          >
            在 GitHub 查看 KAIWU <span aria-hidden="true">↗</span>
          </a>
          <a
            className="text-link"
            href="https://github.com/e29denghy/kaiwu/releases/tag/v0.1.1"
            target="_blank"
            rel="noreferrer"
          >
            阅读 v0.1.1 Release
          </a>
        </div>
      </section>

      <footer>
        <div className="brand footer-brand">
          <span className="brand-mark" aria-hidden="true">开</span>
          <span><strong>开物 KAIWU</strong><small>Human-approved agent workbench</small></span>
        </div>
        <p>Built in the open. Local-first by design.</p>
        <div>
          <a href="https://github.com/e29denghy/kaiwu" target="_blank" rel="noreferrer">GitHub</a>
          <a href="https://github.com/e29denghy/kaiwu/blob/main/SECURITY.md" target="_blank" rel="noreferrer">Security</a>
          <a href="https://github.com/e29denghy/kaiwu/blob/main/LICENSE" target="_blank" rel="noreferrer">License</a>
        </div>
      </footer>
    </main>
  );
}
