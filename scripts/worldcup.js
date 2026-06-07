(() => {
  const STORAGE_KEY = 'copa2026-scores-v3';
  const THEME_KEY = 'copa2026-theme';
  const DATA_URL = '/data/world-cup-2026.json';
  const SCORES_API = '/api/simulator.php';

  const TEAM_META = {
    franca: ['FR', 'Europa', 1],
    espanha: ['ES', 'Europa', 2],
    argentina: ['AR', 'America do Sul', 3],
    inglaterra: ['gb-eng', 'Europa', 4],
    portugal: ['PT', 'Europa', 5],
    brasil: ['BR', 'America do Sul', 6],
    holanda: ['NL', 'Europa', 7],
    belgica: ['BE', 'Europa', 8],
    alemanha: ['DE', 'Europa', 9],
    uruguai: ['UY', 'America do Sul', 10],
    croacia: ['HR', 'Europa', 11],
    colombia: ['CO', 'America do Sul', 12],
    marrocos: ['MA', 'Africa', 13],
    japao: ['JP', 'Asia', 14],
    'estados unidos': ['US', 'America do Norte', 15],
    suica: ['CH', 'Europa', 16],
    senegal: ['SN', 'Africa', 17],
    equador: ['EC', 'America do Sul', 18],
    noruega: ['NO', 'Europa', 19],
    austria: ['AT', 'Europa', 20],
    mexico: ['MX', 'America do Norte', 21],
    turquia: ['TR', 'Europa', 22],
    paraguai: ['PY', 'America do Sul', 23],
    escocia: ['gb-sct', 'Europa', 24],
    australia: ['AU', 'Asia/Oceania', 25],
    'coreia do sul': ['KR', 'Asia', 26],
    suecia: ['SE', 'Europa', 27],
    'costa do marfim': ['CI', 'Africa', 28],
    egito: ['EG', 'Africa', 29],
    ira: ['IR', 'Asia', 30],
    gana: ['GH', 'Africa', 31],
    argelia: ['DZ', 'Africa', 32],
    tunisia: ['TN', 'Africa', 33],
    'arabia saudita': ['SA', 'Asia', 34],
    'africa do sul': ['ZA', 'Africa', 35],
    panama: ['PA', 'America do Norte', 36],
    qatar: ['QA', 'Asia', 37],
    'republica tcheca': ['CZ', 'Europa', 38],
    'bosnia e herzegovina': ['BA', 'Europa', 39],
    iraque: ['IQ', 'Asia', 40],
    uzbequistao: ['UZ', 'Asia', 41],
    'cabo verde': ['CV', 'Africa', 42],
    jordania: ['JO', 'Asia', 43],
    haiti: ['HT', 'America do Norte', 44],
    'nova zelandia': ['NZ', 'Oceania', 45],
    curacao: ['CW', 'America do Norte', 46],
    'republica democratica do congo': ['CD', 'Africa', 47],
    canada: ['CA', 'America do Norte', 48]
  };

  const CONTINENT_COLORS = {
    Europa: '#4f8cff',
    'America do Sul': '#35b66b',
    'America do Norte': '#f0a33a',
    Africa: '#e05d44',
    Asia: '#9b6bff',
    Oceania: '#19a9b7',
    'Asia/Oceania': '#19a9b7'
  };

  let DATA = null;
  let scores = loadScores();
  let remoteScoresReady = false;
  let saveScoresTimer = null;

  const $ = (selector) => document.querySelector(selector);
  const state = {
    search: '',
    group: ''
  };

  function loadScores() {
    try {
      return JSON.parse(localStorage.getItem(STORAGE_KEY)) || {};
    } catch {
      return {};
    }
  }

  function saveScores() {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(scores));
    queueRemoteSave();
  }

  async function loadRemoteScores() {
    try {
      const response = await fetch(SCORES_API, {
        cache: 'no-store',
        credentials: 'same-origin'
      });
      if (!response.ok) return;

      const data = await response.json();
      remoteScoresReady = true;

      if (data.exists) {
        scores = data.scores && typeof data.scores === 'object' ? data.scores : {};
        localStorage.setItem(STORAGE_KEY, JSON.stringify(scores));
        return;
      }

      if (Object.keys(scores).length) {
        await saveRemoteScores();
      }
    } catch {
      remoteScoresReady = false;
    }
  }

  function queueRemoteSave() {
    if (!remoteScoresReady) return;
    window.clearTimeout(saveScoresTimer);
    saveScoresTimer = window.setTimeout(saveRemoteScores, 450);
  }

  async function saveRemoteScores() {
    if (!remoteScoresReady) return;

    try {
      await fetch(SCORES_API, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ scores })
      });
    } catch {
      // Mantem o salvamento local se a conexao cair.
    }
  }

  function slug(value) {
    return String(value)
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase();
  }

  function meta(team) {
    return TEAM_META[slug(team)] || ['un', 'A definir', 99];
  }

  function escapeHTML(value) {
    return String(value).replace(/[&<>"']/g, (char) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    })[char]);
  }

  function flagPath(code) {
    return code.toLowerCase();
  }

  function flagHTML(team) {
    const [code] = meta(team);
    if (code === 'un') {
      return '<span class="flag" aria-hidden="true"></span>';
    }

    const path = flagPath(code);
    return `<img class="flag" src="https://flagcdn.com/w40/${path}.png" srcset="https://flagcdn.com/w80/${path}.png 2x" alt="Bandeira ${escapeHTML(team)}" loading="lazy">`;
  }

  function teamHTML(team, extra = '') {
    const [, continent, rank] = meta(team);
    return `<span class="team ${extra}" style="--continent:${CONTINENT_COLORS[continent] || '#999'}">${flagHTML(team)}<b>${escapeHTML(team)}</b><span class="rank">#${rank}</span></span>`;
  }

  function continentPill(team) {
    const [, continent] = meta(team);
    return `<span class="continent" style="--continent:${CONTINENT_COLORS[continent] || '#999'}">${escapeHTML(continent)}</span>`;
  }

  function rating(team) {
    return DATA.ratings[team] || 65;
  }

  function probs(teamA, teamB) {
    const diff = rating(teamA) - rating(teamB);
    let draw = 0.24 - Math.min(Math.abs(diff) * 0.003, 0.10);
    draw = Math.max(0.14, draw);
    const rem = 1 - draw;
    const home = (1 / (1 + Math.pow(10, -diff / 28))) * rem;
    return [home, draw, rem - home];
  }

  function pct(value) {
    return `${Math.round(value * 100)}%`;
  }

  function expectedScore(teamA, teamB) {
    const [p1, , p2] = probs(teamA, teamB);
    let g1 = 1.15 + p1 * 1.5 - p2 * 0.45 + (rating(teamA) - 70) / 100;
    let g2 = 1.15 + p2 * 1.5 - p1 * 0.45 + (rating(teamB) - 70) / 100;
    g1 = Math.max(0, Math.round(g1));
    g2 = Math.max(0, Math.round(g2));

    if (g1 === g2 && Math.max(p1, p2) > 0.49) {
      if (p1 > p2) g1 += 1;
      else g2 += 1;
    }

    return [g1, g2];
  }

  function standings() {
    const table = {};

    Object.entries(DATA.groups).forEach(([group, teams]) => {
      table[group] = {};
      teams.forEach((team) => {
        table[group][team] = {
          team,
          group,
          J: 0,
          V: 0,
          E: 0,
          D: 0,
          GP: 0,
          GC: 0,
          SG: 0,
          Pts: 0,
          probClass: 0,
          probPos: [0, 0, 0, 0]
        };
      });
    });

    DATA.matches.forEach((match) => {
      const score = scores[match.id];
      if (!score || score.a === '' || score.b === '') return;

      const a = Number(score.a);
      const b = Number(score.b);
      if (!Number.isFinite(a) || !Number.isFinite(b)) return;

      const teamA = table[match.group][match.team1];
      const teamB = table[match.group][match.team2];
      teamA.J += 1;
      teamB.J += 1;
      teamA.GP += a;
      teamA.GC += b;
      teamB.GP += b;
      teamB.GC += a;
      teamA.SG = teamA.GP - teamA.GC;
      teamB.SG = teamB.GP - teamB.GC;

      if (a > b) {
        teamA.V += 1;
        teamA.Pts += 3;
        teamB.D += 1;
      } else if (a < b) {
        teamB.V += 1;
        teamB.Pts += 3;
        teamA.D += 1;
      } else {
        teamA.E += 1;
        teamB.E += 1;
        teamA.Pts += 1;
        teamB.Pts += 1;
      }
    });

    Object.entries(table).forEach(([group, rows]) => {
      const sorted = Object.values(rows).sort((a, b) =>
        b.Pts - a.Pts ||
        b.SG - a.SG ||
        b.GP - a.GP ||
        rating(b.team) - rating(a.team)
      );

      sorted.forEach((row, index) => {
        const base = index < 2 ? 0.86 : index === 2 ? 0.40 : 0.12;
        row.probClass = Math.min(0.98, Math.max(0.03, base + (rating(row.team) - 72) / 80));
        row.probPos = positionProbs(row, index);
      });

      table[group] = sorted;
    });

    return table;
  }

  function positionProbs(row, index) {
    const strength = Math.max(0.05, Math.min(0.95, (rating(row.team) - 55) / 45));
    const playedBoost = Math.min(0.18, row.Pts * 0.025 + row.SG * 0.01);
    let p1 = Math.max(0.02, strength * 0.45 + (3 - index) * 0.08 + playedBoost);
    let p2 = Math.max(0.03, strength * 0.31 + (2 - index) * 0.06 + playedBoost * 0.7);
    let p3 = Math.max(0.03, (1 - strength) * 0.22 + (index === 2 ? 0.22 : 0.06) - playedBoost * 0.25);
    let p4 = Math.max(0.02, 1 - strength * 0.7 - p1 * 0.5 - p2 * 0.3);
    const sum = p1 + p2 + p3 + p4;
    return [p1 / sum, p2 / sum, p3 / sum, p4 / sum];
  }

  function matchFilter(match) {
    const q = state.search;
    return (!state.group || match.group === state.group) &&
      (!q || slug(match.team1).includes(q) || slug(match.team2).includes(q));
  }

  function renderStandings() {
    const current = standings();
    const groups = Object.entries(current).filter(([group, rows]) => {
      if (state.group && state.group !== group) return false;
      if (!state.search) return true;
      return rows.some((row) => slug(row.team).includes(state.search));
    });

    $('#wcStandings').innerHTML = groups.map(([group, rows]) => `
      <article class="group-card">
        <h3>Grupo ${group}<span class="small">top 2 + melhores terceiros</span></h3>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Pos</th><th>Seleção</th><th>Cont.</th>
                <th class="numeric">Pts</th><th class="numeric">J</th><th class="numeric">V</th><th class="numeric">E</th><th class="numeric">D</th>
                <th class="numeric">GP</th><th class="numeric">GC</th><th class="numeric">SG</th><th>Class.</th>
              </tr>
            </thead>
            <tbody>
              ${rows.map((row, index) => `
                <tr class="${index < 2 ? 'qual' : index === 2 ? 'third' : ''}">
                  <td>${index + 1}</td>
                  <td>${teamHTML(row.team)}</td>
                  <td>${continentPill(row.team)}</td>
                  <td class="numeric">${row.Pts}</td>
                  <td class="numeric">${row.J}</td>
                  <td class="numeric">${row.V}</td>
                  <td class="numeric">${row.E}</td>
                  <td class="numeric">${row.D}</td>
                  <td class="numeric">${row.GP}</td>
                  <td class="numeric">${row.GC}</td>
                  <td class="numeric">${row.SG}</td>
                  <td><div class="prob"><b>${pct(row.probClass)}</b><span class="bar-track"><span class="bar-fill" style="width:${pct(row.probClass)}"></span></span></div></td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      </article>
    `).join('') || '<div class="empty">Nenhum grupo encontrado.</div>';

    const thirds = Object.values(current)
      .map((rows) => rows[2])
      .sort((a, b) => b.Pts - a.Pts || b.SG - a.SG || b.GP - a.GP || rating(b.team) - rating(a.team));

    $('#wcThirds').innerHTML = `
      <div class="table-wrap">
        <table>
          <thead><tr><th>Pos</th><th>Grupo</th><th>Seleção</th><th class="numeric">Pts</th><th class="numeric">SG</th><th class="numeric">GP</th><th>Status</th></tr></thead>
          <tbody>
            ${thirds.map((row, index) => `
              <tr class="${index < 8 ? 'third' : ''}">
                <td>${index + 1}</td>
                <td>${row.group}</td>
                <td>${teamHTML(row.team)}</td>
                <td class="numeric">${row.Pts}</td>
                <td class="numeric">${row.SG}</td>
                <td class="numeric">${row.GP}</td>
                <td>${index < 8 ? 'Dentro dos 8 melhores terceiros' : 'Fora no momento'}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>`;
  }

  function renderSchedule() {
    const groups = Object.keys(DATA.groups)
      .filter((group) => !state.group || state.group === group)
      .map((group) => {
        const matches = DATA.matches.filter((match) => match.group === group && matchFilter(match));
        if (!matches.length) return '';

        return `<div class="schedule-group">
          <h3>Grupo ${group}</h3>
          <div class="schedule-grid">
            ${matches.map((match) => {
              const [p1, draw, p2] = probs(match.team1, match.team2);
              const favorite = p1 >= p2 ? match.team1 : match.team2;
              return `<article class="schedule-card">
                <div class="card-meta"><span class="tag">Grupo ${match.group}</span><span>${match.date} - ${match.time}</span></div>
                <div class="versus-line">${teamHTML(match.team1)}<span class="vs">VS</span>${teamHTML(match.team2, 'away')}</div>
                <p class="winner">Favorito estimado: <b>${escapeHTML(favorite)}</b></p>
                <div class="prob-rows">
                  ${probRow(match.team1, p1, 'win')}
                  ${probRow('Empate', draw, 'draw')}
                  ${probRow(match.team2, p2, 'lose')}
                </div>
              </article>`;
            }).join('')}
          </div>
        </div>`;
      }).join('');

    $('#wcSchedule').innerHTML = groups || '<div class="empty">Nenhum jogo encontrado.</div>';
  }

  function probRow(label, value, tone = '') {
    return `<div class="prob-row"><span>${escapeHTML(label)}</span><span class="bar-track"><span class="bar-fill ${tone}" style="width:${pct(value)}"></span></span><b>${pct(value)}</b></div>`;
  }

  function renderMatches() {
    const matches = DATA.matches.filter(matchFilter);

    $('#wcMatches').innerHTML = matches.map((match) => {
      const [p1, draw, p2] = probs(match.team1, match.team2);
      const score = scores[match.id] || { a: '', b: '' };

      return `<article class="match-card">
        <div class="card-meta"><span>Grupo ${match.group} - ${match.date} - ${match.time}</span><span>Jogo ${match.id}</span></div>
        <div class="match-score">
          ${teamHTML(match.team1)}
          <input class="score-input" type="number" min="0" inputmode="numeric" value="${escapeHTML(score.a)}" data-score-id="${match.id}" data-score-side="a" aria-label="Gols ${escapeHTML(match.team1)}">
          <span class="vs">x</span>
          <input class="score-input" type="number" min="0" inputmode="numeric" value="${escapeHTML(score.b)}" data-score-id="${match.id}" data-score-side="b" aria-label="Gols ${escapeHTML(match.team2)}">
          ${teamHTML(match.team2, 'away')}
        </div>
        <div class="pills">
          <span class="pill win">${escapeHTML(match.team1)}: ${pct(p1)}</span>
          <span class="pill draw">Empate: ${pct(draw)}</span>
          <span class="pill lose">${escapeHTML(match.team2)}: ${pct(p2)}</span>
        </div>
      </article>`;
    }).join('') || '<div class="empty">Nenhum jogo encontrado.</div>';
  }

  function renderKnockout() {
    const bracket = buildKnockoutBracket();
    const rounds = [
      ['16 avos', 0, 16],
      ['Oitavas', 16, 24],
      ['Quartas', 24, 28],
      ['Semifinais', 28, 30],
      ['Decisão', 30, 32]
    ];

    $('#wcKnockout').innerHTML = rounds.map(([title, start, end]) => `
      <section class="ko-round">
        <h3>${title}</h3>
        <div class="knockout-grid">
          ${bracket.slice(start, end).map((match) => knockoutCard(match)).join('')}
        </div>
      </section>
    `).join('');
  }

  function cleanSlot(slot) {
    return String(slot)
      .replaceAll('\u00c3\u201a\u00c2\u00ba', '\u00ba')
      .replaceAll('\u00c2\u00ba', '\u00ba');
  }

  function buildKnockoutBracket() {
    const current = standings();
    const thirds = Object.values(current)
      .map((rows) => rows[2])
      .sort((a, b) => b.Pts - a.Pts || b.SG - a.SG || b.GP - a.GP || rating(b.team) - rating(a.team));
    let usedThird = 0;
    const bracket = DATA.knockouts.map((match, index) => ({ ...match, index, teamA: null, teamB: null }));

    function resolveGroupSlot(slot) {
      const normalized = cleanSlot(slot);
      const first = normalized.match(/^1\u00ba Grupo ([A-L])$/);
      if (first) return current[first[1]][0].team;

      const second = normalized.match(/^2\u00ba Grupo ([A-L])$/);
      if (second) return current[second[1]][1].team;

      if (normalized === 'Melhor 3\u00ba') {
        const team = thirds[usedThird % thirds.length]?.team || 'A definir';
        usedThird += 1;
        return team;
      }

      return normalized;
    }

    for (let index = 0; index < 16; index += 1) {
      bracket[index].teamA = resolveGroupSlot(bracket[index].team1);
      bracket[index].teamB = resolveGroupSlot(bracket[index].team2);
    }

    for (let index = 16; index < 24; index += 1) {
      const source = (index - 16) * 2;
      bracket[index].teamA = knockoutResult(bracket[source]).winner || `Vencedor jogo ${source + 1}`;
      bracket[index].teamB = knockoutResult(bracket[source + 1]).winner || `Vencedor jogo ${source + 2}`;
    }

    for (let index = 24; index < 28; index += 1) {
      const source = 16 + (index - 24) * 2;
      bracket[index].teamA = knockoutResult(bracket[source]).winner || `Vencedor jogo ${source + 1}`;
      bracket[index].teamB = knockoutResult(bracket[source + 1]).winner || `Vencedor jogo ${source + 2}`;
    }

    for (let index = 28; index < 30; index += 1) {
      const source = 24 + (index - 28) * 2;
      bracket[index].teamA = knockoutResult(bracket[source]).winner || `Vencedor jogo ${source + 1}`;
      bracket[index].teamB = knockoutResult(bracket[source + 1]).winner || `Vencedor jogo ${source + 2}`;
    }

    bracket[30].teamA = knockoutResult(bracket[28]).loser || 'Perdedor semifinal 1';
    bracket[30].teamB = knockoutResult(bracket[29]).loser || 'Perdedor semifinal 2';
    bracket[31].teamA = knockoutResult(bracket[28]).winner || 'Vencedor semifinal 1';
    bracket[31].teamB = knockoutResult(bracket[29]).winner || 'Vencedor semifinal 2';

    return bracket;
  }

  function scoreFor(match) {
    return scores[`ko-${match.index}`] || { a: '', b: '' };
  }

  function isPlaceholder(team) {
    return /^(A definir|Vencedor|Perdedor)/.test(team || '');
  }

  function knockoutResult(match) {
    if (!match?.teamA || !match?.teamB || isPlaceholder(match.teamA) || isPlaceholder(match.teamB)) {
      return { winner: null, loser: null, status: 'Aguardando classificados' };
    }

    const score = scoreFor(match);
    if (score.a === '' || score.b === '') {
      return { winner: null, loser: null, status: 'Preencha o placar' };
    }

    const a = Number(score.a);
    const b = Number(score.b);
    if (!Number.isFinite(a) || !Number.isFinite(b)) {
      return { winner: null, loser: null, status: 'Placar inválido' };
    }

    if (a === b) {
      return { winner: null, loser: null, status: 'Defina desempate' };
    }

    const winner = a > b ? match.teamA : match.teamB;
    const loser = a > b ? match.teamB : match.teamA;
    return { winner, loser, status: `Avança: ${winner}` };
  }

  function knockoutCard(match) {
    const score = scoreFor(match);
    const result = knockoutResult(match);
    const disabled = isPlaceholder(match.teamA) || isPlaceholder(match.teamB) ? 'disabled' : '';

    return `<article class="ko-card ${result.winner ? 'decided' : ''}">
      <div class="card-meta"><span>${escapeHTML(cleanSlot(match.fase))}</span><span>Jogo ${match.index + 1}</span></div>
      <div class="small">${match.date} - ${match.time}</div>
      <div class="ko-score">
        <div class="ko-team-line">
          ${teamHTML(match.teamA || 'A definir')}
          <input class="score-input" type="number" min="0" inputmode="numeric" value="${escapeHTML(score.a)}" data-score-id="ko-${match.index}" data-score-side="a" aria-label="Gols ${escapeHTML(match.teamA || 'time 1')}" ${disabled}>
        </div>
        <span class="ko-vs">x</span>
        <div class="ko-team-line away">
          <input class="score-input" type="number" min="0" inputmode="numeric" value="${escapeHTML(score.b)}" data-score-id="ko-${match.index}" data-score-side="b" aria-label="Gols ${escapeHTML(match.teamB || 'time 2')}" ${disabled}>
          ${teamHTML(match.teamB || 'A definir', 'away')}
        </div>
      </div>
      <div class="ko-status">${escapeHTML(result.status)}</div>
    </article>`;
  }
  function phaseProjection(row) {
    const base = row.probClass;
    const strength = Math.max(0.05, Math.min(0.96, (rating(row.team) - 55) / 45));
    const oit = base;
    const quart = oit * (0.34 + strength * 0.34);
    const semi = quart * (0.30 + strength * 0.30);
    const fin = semi * (0.26 + strength * 0.28);
    const title = fin * (0.22 + strength * 0.30);
    return { oit, quart, semi, fin, title };
  }

  function renderStats() {
    const current = standings();
    const teams = Object.values(current).flat()
      .filter((row) => (!state.group || row.group === state.group) && (!state.search || slug(row.team).includes(state.search)))
      .sort((a, b) => phaseProjection(b).title - phaseProjection(a).title);

    $('#wcStats').innerHTML = teams.map((row) => {
      const projection = phaseProjection(row);
      const rows = [
        ['1º grupo', row.probPos[0]],
        ['2º grupo', row.probPos[1]],
        ['3º grupo', row.probPos[2]],
        ['4º grupo', row.probPos[3]],
        ['Oitavas', projection.oit],
        ['Quartas', projection.quart],
        ['Semifinal', projection.semi],
        ['Final', projection.fin],
        ['Título', projection.title]
      ];

      return `<article class="stat-card">
        <div class="stat-head"><div>${teamHTML(row.team)}</div><span class="small">Grupo ${row.group}</span></div>
        <div class="stat-rows">
          ${rows.map(([label, value]) => `<div class="stat-row"><span>${label}</span><span class="bar-track"><span class="bar-fill" style="width:${pct(value)}"></span></span><b>${pct(value)}</b></div>`).join('')}
        </div>
      </article>`;
    }).join('') || '<div class="empty">Nenhuma seleção encontrada.</div>';
  }

  function renderAll() {
    renderStandings();
    renderSchedule();
    renderMatches();
    renderKnockout();
    renderStats();
  }

  function setScore(id, side, value) {
    scores[id] = scores[id] || { a: '', b: '' };
    scores[id][side] = value === '' ? '' : Math.max(0, Number(value));
    saveScores();
    saveRemoteScores();
    renderAll();
  }

  function autofill() {
    DATA.matches.forEach((match) => {
      const [a, b] = expectedScore(match.team1, match.team2);
      scores[match.id] = { a, b };
    });
    saveScores();
    saveRemoteScores();
    renderAll();
  }

  function clearScores() {
    scores = {};
    saveScores();
    saveRemoteScores();
    renderAll();
  }

  function applyTheme() {
    const theme = localStorage.getItem(THEME_KEY) || 'dark';
    document.documentElement.dataset.theme = theme;
    const button = $('[data-action="theme"]');
    if (button) button.textContent = theme === 'dark' ? 'Tema claro' : 'Tema escuro';
  }

  function toggleTheme() {
    const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
    localStorage.setItem(THEME_KEY, next);
    applyTheme();
  }

  function bindEvents() {
    $('#wcSearch')?.addEventListener('input', (event) => {
      state.search = slug(event.target.value.trim());
      renderAll();
    });

    $('#wcGroup')?.addEventListener('change', (event) => {
      state.group = event.target.value;
      renderAll();
    });

    document.addEventListener('input', (event) => {
      const target = event.target;
      if (!target.matches('[data-score-id]')) return;
      setScore(target.dataset.scoreId, target.dataset.scoreSide, target.value);
    });

    document.addEventListener('click', (event) => {
      const action = event.target.closest('[data-action]')?.dataset.action;
      if (action === 'autofill') autofill();
      if (action === 'clear') clearScores();
      if (action === 'theme') toggleTheme();

      const tab = event.target.closest('[data-tab]');
      if (tab) {
        document.querySelectorAll('[data-tab]').forEach((button) => button.classList.remove('active'));
        document.querySelectorAll('[data-panel]').forEach((panel) => panel.classList.add('hidden'));
        tab.classList.add('active');
        document.querySelector(`[data-panel="${tab.dataset.tab}"]`)?.classList.remove('hidden');
      }
    });
  }

  async function init() {
    applyTheme();
    bindEvents();

    const response = await fetch(DATA_URL, { cache: 'no-store' });
    if (!response.ok) throw new Error(`Falha ao carregar ${DATA_URL}`);
    DATA = await response.json();
    await loadRemoteScores();

    const groupSelect = $('#wcGroup');
    Object.keys(DATA.groups).forEach((group) => {
      const option = document.createElement('option');
      option.value = group;
      option.textContent = `Grupo ${group}`;
      groupSelect.appendChild(option);
    });

    renderAll();
  }

  init().catch((error) => {
    console.error(error);
    const shell = $('.wc-shell');
    if (shell) shell.insertAdjacentHTML('afterbegin', '<div class="empty">Não foi possível carregar os dados da Copa.</div>');
  });
})();
