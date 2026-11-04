(() => {
  "use strict";
  let data = JSON.parse(document.getElementById("roadmap-data").textContent);
  const $ = (id) => document.getElementById(id);
  const labels = {PENDIENTE: "Pendiente", EN_CURSO: "En curso", PARCIAL: "Parcial", HECHO: "Hecho", BLOQUEADO: "Bloqueado", EXCLUIDO: "Fuera del alcance"};
  const scopes = {SOLICITADO: "Solicitado", PROPUESTO: "Propuesto", EXCLUIDO: "Excluido"};
  const colors = {PENDIENTE: "#65788c", EN_CURSO: "#245ca6", PARCIAL: "#996308", HECHO: "#18765b", BLOQUEADO: "#b13d48", EXCLUIDO: "#7a559d"};
  const backgrounds = {PENDIENTE: "#eef1f5", EN_CURSO: "#e9f1ff", PARCIAL: "#fff3d8", HECHO: "#e5f5eb", BLOQUEADO: "#fdebed", EXCLUIDO: "#f2ebfa"};
  function colorize(node, status) {
    node.style.setProperty("--status", colors[status]);
    node.style.setProperty("--status-bg", backgrounds[status]);
  }
  const tasks = new Map(data.tasks.map((task) => [task.id, task]));
  const dependents = new Map(data.tasks.map((task) => [task.id, []]));
  data.edges.forEach((edge) => dependents.get(edge.from).push(edge.to));
  const states = new Set(data.states);
  const normalize = (value) => value.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
  const viewport = $("viewport");
  let selected = null, chain = null, visible = [], positions = new Map();
  let scale = 1, pan = {x: 0, y: 0}, bounds = {width: 1, height: 1}, drag = null;
  const element = (tag, text, className) => {
    const node = document.createElement(tag);
    if (text !== undefined) node.textContent = text;
    if (className) node.className = className;
    return node;
  };
  const dot = (status) => {const node = element("span", undefined, "dot"); node.style.setProperty("--status", colors[status]); node.setAttribute("aria-hidden", "true"); return node;};
  function closure(id, direction) {
    const found = new Set([id]), pending = [id];
    while (pending.length) {
      const current = pending.pop();
      const next = direction === "before" ? tasks.get(current).dependencies : dependents.get(current);
      next.forEach((related) => {if (!found.has(related)) {found.add(related); pending.push(related);}});
    }
    return found;
  }
  function chainIds(id) {return new Set([...closure(id, "before"), ...closure(id, "after")]);}
  const fullLevels = new Map();
  function level(id) {
    if (!fullLevels.has(id)) fullLevels.set(id, Math.max(-1, ...tasks.get(id).dependencies.map(level)) + 1);
    return fullLevels.get(id);
  }
  data.tasks.forEach((task) => level(task.id));
  data.states.forEach((status) => {
    const button = element("button", undefined, "state-card");
    button.type = "button";
    colorize(button, status);
    button.dataset.status = status;
    button.setAttribute("aria-pressed", "true");
    button.setAttribute("aria-label", `${labels[status]}: ${data.tasks.filter((task) => task.status === status).length} tareas`);
    button.append(element("span", String(data.tasks.filter((task) => task.status === status).length), "count"), dot(status), element("span", labels[status], "state-name"));
    button.addEventListener("click", () => {states.has(status) ? states.delete(status) : states.add(status); chain = null; render();});
    $("states").append(button);
  });
  data.modules.forEach((module) => {const option = element("option", module.title); option.value = module.id; $("module").append(option);});
  function transform() {
    $("scene").style.transform = `translate(${pan.x}px, ${pan.y}px) scale(${scale})`;
    $("zoom-label").textContent = `${Math.round(scale * 100)}%`;
  }
  function fit() {
    if (!visible.length) return;
    scale = Math.min(1, Math.max(.04, Math.min((viewport.clientWidth - 50) / bounds.width, (viewport.clientHeight - 50) / bounds.height)));
    pan = {x: (viewport.clientWidth - bounds.width * scale) / 2, y: (viewport.clientHeight - bounds.height * scale) / 2};
    transform();
  }
  function readableFrame() {
    fit();
    if (scale < .65) {
      scale = .65;
      pan = {x: 24, y: (viewport.clientHeight - bounds.height * scale) / 2};
      transform();
    }
  }
  function zoom(factor, x = viewport.clientWidth / 2, y = viewport.clientHeight / 2) {
    const previous = scale;
    scale = Math.min(2, Math.max(.04, scale * factor));
    pan = {x: x - (x - pan.x) * scale / previous, y: y - (y - pan.y) * scale / previous};
    transform();
  }
  function center(id) {
    const position = positions.get(id);
    if (!position) return;
    scale = Math.max(.85, scale);
    pan = {x: viewport.clientWidth / 2 - (position.x + 98) * scale, y: viewport.clientHeight / 2 - (position.y + 53) * scale};
    transform();
  }
  function resetFilters() {
    $("search").value = ""; $("module").value = ""; $("scope").value = "";
    data.states.forEach((status) => states.add(status));
    chain = null;
  }
  function select(id, fromList = false, fromRelation = false) {
    selected = id;
    if (fromRelation) {resetFilters(); render(false);}
    else {paintSelection(); renderDetail();}
    if (fromList || fromRelation) center(id);
  }
  function paintSelection() {
    const related = selected ? chainIds(selected) : new Set();
    document.querySelectorAll("[data-task]").forEach((node) => {
      node.classList.toggle("selected", node.dataset.task === selected);
      node.setAttribute("aria-pressed", String(node.dataset.task === selected));
      if (node.classList.contains("node")) node.classList.toggle("related", related.has(node.dataset.task));
    });
    document.querySelectorAll(".edge").forEach((edge) => edge.classList.toggle("related", related.has(edge.dataset.from) && related.has(edge.dataset.to)));
  }
  function drawGraph() {
    positions = new Map();
    $("nodes").replaceChildren(); $("connections").replaceChildren();
    const levels = [...new Set(visible.map((task) => fullLevels.get(task.id)))].sort((a, b) => a - b);
    const columns = levels.map((value) => visible.filter((task) => fullLevels.get(task.id) === value));
    const rows = Math.max(1, ...columns.map((column) => column.length));
    columns.forEach((column, columnIndex) => {
      const averageParent = (task) => {
        const ys = task.dependencies.filter((id) => positions.has(id)).map((id) => positions.get(id).y);
        return ys.length ? ys.reduce((sum, y) => sum + y, 0) / ys.length : 0;
      };
      column.sort((a, b) => averageParent(a) - averageParent(b) || a.id.localeCompare(b.id));
      const heading = element("span", `Nivel ${levels[columnIndex] + 1}`, "level-label");
      heading.style.left = `${columnIndex * 270 + 24}px`; heading.style.top = "10px";
      $("nodes").append(heading);
      column.forEach((task, row) => {
        const position = {x: columnIndex * 270 + 24, y: 44 + row * 134 + (rows - column.length) * 67};
        positions.set(task.id, position);
        const button = element("button", undefined, "node");
        button.dataset.task = task.id;
        button.style.left = `${position.x}px`; button.style.top = `${position.y}px`;
        colorize(button, task.status);
        button.setAttribute("aria-label", `${task.id}, ${task.title}, ${labels[task.status]}`);
        button.title = `${task.id} · ${task.title} · ${labels[task.status]}`;
        button.append(element("span", task.id, "node-id"), element("span", task.title, "node-title"), element("span", `${labels[task.status]} · ${scopes[task.scope]}`, "node-status"));
        button.addEventListener("click", () => select(task.id));
        button.addEventListener("focus", () => {if (document.activeElement.matches(":focus-visible")) center(task.id);});
        $("nodes").append(button);
      });
    });
    bounds = {width: Math.max(240, levels.length * 270 - 26), height: rows * 134 + 50};
    const svg = $("connections"), ns = "http://www.w3.org/2000/svg";
    svg.setAttribute("width", bounds.width); svg.setAttribute("height", bounds.height);
    const defs = document.createElementNS(ns, "defs");
    data.states.forEach((status) => {
      const marker = document.createElementNS(ns, "marker");
      Object.entries({id: `arrow-${status}`, viewBox: "0 0 10 10", refX: "9", refY: "5", markerWidth: "6", markerHeight: "6", orient: "auto-start-reverse"}).forEach(([name, value]) => marker.setAttribute(name, value));
      const arrow = document.createElementNS(ns, "path"); arrow.setAttribute("d", "M 0 0 L 10 5 L 0 10 z"); arrow.setAttribute("fill", colors[status]); marker.append(arrow); defs.append(marker);
    });
    svg.append(defs);
    let drawn = 0, hidden = 0;
    data.edges.forEach((edge) => {
      const from = positions.get(edge.from), to = positions.get(edge.to);
      if (!from || !to) {if (from || to) hidden++; return;}
      const path = document.createElementNS(ns, "path");
      const startX = from.x + 198, startY = from.y + 53, endX = to.x - 5, endY = to.y + 53;
      const control = Math.max(30, (endX - startX) / 2);
      path.setAttribute("d", `M${startX},${startY} C${startX + control},${startY} ${endX - control},${endY} ${endX},${endY}`);
      const status = tasks.get(edge.to).status;
      colorize(path, status);
      path.setAttribute("class", "edge"); path.setAttribute("marker-end", `url(#arrow-${status})`);
      path.dataset.from = edge.from; path.dataset.to = edge.to;
      svg.append(path); drawn++;
    });
    $("graph-count").textContent = `${visible.length} tareas · ${drawn} conexiones${hidden ? ` · ${hidden} fuera del filtro` : ""}${chain ? ` · Cadena de ${chain}` : ""}`;
    $("empty").hidden = visible.length > 0;
  }
  function relationSection(title, ids) {
    const section = element("section"); section.append(element("h3", `${title} (${ids.length})`));
    const list = element("div", undefined, "relation-list");
    ids.forEach((id) => {
      const button = element("button", id); button.title = `${tasks.get(id).title} · ${labels[tasks.get(id).status]}`;
      button.addEventListener("click", () => select(id, false, true)); list.append(button);
    });
    if (!ids.length) list.append(element("p", "Sin tareas con ID explícito."));
    section.append(list); return section;
  }
  function renderDetail() {
    document.querySelector(".workspace").classList.toggle("has-detail", Boolean(selected));
    if (!selected) {
      $("detail").replaceChildren();
      const placeholder = element("div", undefined, "detail-placeholder");
      placeholder.append(element("span", "EXPLORAR EL PLAN", "eyebrow"), element("h2", "Elegí una tarea"), element("p", "Consultá sus requisitos, las tareas que dependen de ella y sus criterios de aceptación."), element("p", "Usá la lista para centrar una tarea en el árbol."));
      $("detail").append(placeholder); return;
    }
    const task = tasks.get(selected), header = element("div", undefined, "detail-header"), body = element("div", undefined, "detail-body");
    const close = element("button", "Cerrar"); close.setAttribute("aria-label", "Cerrar detalle de tarea"); close.addEventListener("click", closeDetail);
    header.append(element("strong", task.id), close);
    const badge = element("span", undefined, "badge"); colorize(badge, task.status); badge.append(dot(task.status), element("span", `${labels[task.status]} · ${scopes[task.scope]}`));
    body.append(badge, element("h2", task.title));
    const module = data.modules.find((item) => item.id === task.module); body.append(element("p", module.title));
    if (chain || !visible.some((item) => item.id === task.id)) body.append(element("p", chain ? `Cadena completa de ${chain}; los filtros quedan suspendidos hasta salir.` : "Esta tarea quedó fuera de los filtros actuales."));
    const actions = element("div", undefined, "detail-actions"), branch = element("button", chain ? "Salir de la cadena" : "Ver cadena completa");
    branch.addEventListener("click", () => {chain = chain ? null : task.id; render();}); actions.append(branch); body.append(actions);
    body.append(relationSection("Requisitos", task.dependencies), relationSection("Dependientes", dependents.get(task.id)));
    for (const label of data.fieldNames) {
      body.append(element("h3", label === "Dependencias" ? "Dependencias: texto del plan" : label), element("p", task.fields[label]));
    }
    body.append(element("h3", "Especificación fuente"));
    const source = element("a", `${task.source} · línea ${task.line}`); source.href = task.sourceUrl; source.target = "_blank"; source.rel = "noopener"; body.append(source);
    $("detail").replaceChildren(header, body); $("detail").scrollTop = 0;
  }
  function closeDetail() {
    const previous = selected; selected = null;
    if (chain) {chain = null; render();} else {paintSelection(); renderDetail();}
    const listButton = [...document.querySelectorAll(".list-task")].find((button) => button.dataset.task === previous);
    (listButton || $("search")).focus({preventScroll: true});
  }
  function render(reframe = true) {
    const query = normalize($("search").value.trim()), module = $("module").value, scope = $("scope").value;
    const branch = chain ? chainIds(chain) : null;
    visible = data.tasks.filter((task) => branch ? branch.has(task.id) : states.has(task.status) && (!module || task.module === module) && (!scope || task.scope === scope) && (!query || normalize(`${task.id} ${task.title}`).includes(query)));
    document.querySelectorAll("[data-status]").forEach((button) => button.setAttribute("aria-pressed", String(states.has(button.dataset.status))));
    $("result-count").textContent = `${visible.length} / ${data.tasks.length}`;
    $("task-list").replaceChildren();
    visible.forEach((task) => {
      const button = element("button", undefined, "list-task"); button.dataset.task = task.id; colorize(button, task.status);
      const meta = element("span", undefined, "list-meta"); meta.append(dot(task.status), element("span", task.id), element("span", labels[task.status], "list-status"));
      button.append(meta, element("span", task.title, "list-title")); button.addEventListener("click", () => select(task.id, true)); $("task-list").append(button);
    });
    if (!visible.length) $("task-list").append(element("p", "No hay coincidencias. Restablecé los filtros para volver al plan completo.", "no-results"));
    drawGraph(); paintSelection(); renderDetail(); if (reframe) readableFrame();
  }
  $("filters").addEventListener("submit", (event) => event.preventDefault());
  for (const id of ["search", "module", "scope"]) $(id).addEventListener(id === "search" ? "input" : "change", () => {chain = null; render();});
  $("reset").addEventListener("click", () => {resetFilters(); selected = null; render(); $("search").focus();});
  $("zoom-in").addEventListener("click", () => zoom(1.3)); $("zoom-out").addEventListener("click", () => zoom(1 / 1.3)); $("fit").addEventListener("click", fit);
  viewport.addEventListener("pointerdown", (event) => {
    if (event.target.closest("button") || event.button !== 0) return;
    drag = {id: event.pointerId, x: event.clientX, y: event.clientY, pan: {...pan}};
    viewport.setPointerCapture(event.pointerId); viewport.classList.add("dragging"); viewport.focus({preventScroll: true});
  });
  viewport.addEventListener("pointermove", (event) => {if (drag && drag.id === event.pointerId) {pan = {x: drag.pan.x + event.clientX - drag.x, y: drag.pan.y + event.clientY - drag.y}; transform();}});
  const endDrag = () => {drag = null; viewport.classList.remove("dragging");};
  viewport.addEventListener("pointerup", endDrag); viewport.addEventListener("pointercancel", endDrag); viewport.addEventListener("lostpointercapture", endDrag);
  viewport.addEventListener("wheel", (event) => {
    event.preventDefault();
    if (event.ctrlKey || event.metaKey) {const rect = viewport.getBoundingClientRect(); zoom(Math.exp(-event.deltaY * .006), event.clientX - rect.left, event.clientY - rect.top);}
    else {pan.x -= event.deltaX; pan.y -= event.deltaY; transform();}
  }, {passive: false});
  viewport.addEventListener("keydown", (event) => {
    if (event.target !== viewport) return;
    const deltas = {ArrowLeft: [60, 0], ArrowRight: [-60, 0], ArrowUp: [0, 60], ArrowDown: [0, -60]};
    if (deltas[event.key]) {event.preventDefault(); pan.x += deltas[event.key][0]; pan.y += deltas[event.key][1]; transform();}
    if (["+", "=", "-"].includes(event.key)) {event.preventDefault(); zoom(event.key === "-" ? 1 / 1.3 : 1.3);}
    if (event.key === "Home") {event.preventDefault(); fit();}
  });
  $("help-open").addEventListener("click", () => $("help").showModal());
  const closeHelp = () => {$("help").close(); $("help-open").focus();};
  $("help-close").addEventListener("click", closeHelp); $("help-done").addEventListener("click", closeHelp);
  $("help").addEventListener("cancel", (event) => {event.preventDefault(); closeHelp();});
  document.addEventListener("keydown", (event) => {if (event.key === "Escape" && !$("help").open && selected) {event.preventDefault(); closeDetail();}});
  new ResizeObserver(() => selected && positions.has(selected) ? center(selected) : readableFrame()).observe(viewport);
  render();

  function liveStatus(mode, text, error = "") {
    const badge = $("live-status");
    badge.dataset.mode = mode;
    if (badge.textContent !== text) badge.textContent = text;
    badge.title = error;
    $("live-error").hidden = !error;
    $("live-error").textContent = error;
  }
  function applyUpdate(next) {
    const previous = {pan: {...pan}, scale, detailScroll: $("detail").scrollTop};
    const focused = document.activeElement;
    const focusedTask = focused?.dataset.task;
    const focusedClass = focused?.classList.contains("list-task") ? ".list-task" : ".node";
    data = next;
    document.title = `Árbol de tareas · ${data.projectName}`;
    $("project-name").textContent = data.projectName;
    tasks.clear(); dependents.clear(); fullLevels.clear();
    data.tasks.forEach((task) => {tasks.set(task.id, task); dependents.set(task.id, []);});
    data.edges.forEach((edge) => dependents.get(edge.from).push(edge.to));
    data.tasks.forEach((task) => level(task.id));
    if (selected && !tasks.has(selected)) selected = null;
    if (chain && !tasks.has(chain)) chain = null;
    const currentModule = $("module").value;
    $("module").replaceChildren(element("option", "Todos los módulos"));
    $("module").firstChild.value = "";
    data.modules.forEach((module) => {const option = element("option", module.title); option.value = module.id; $("module").append(option);});
    $("module").value = data.modules.some((module) => module.id === currentModule) ? currentModule : "";
    document.querySelectorAll(".state-card").forEach((button) => {
      const count = data.tasks.filter((task) => task.status === button.dataset.status).length;
      button.querySelector(".count").textContent = count;
      button.setAttribute("aria-label", `${labels[button.dataset.status]}: ${count} tareas`);
    });
    render(false);
    $("detail").scrollTop = previous.detailScroll;
    if (focusedTask) {
      const replacement = [...document.querySelectorAll(focusedClass)].find((node) => node.dataset.task === focusedTask);
      (replacement || $("search")).focus({preventScroll: true});
    }
    scale = previous.scale; pan = previous.pan; transform();
    document.querySelector(".snapshot time").textContent = data.generatedAt;
    $("roadmap-data").textContent = JSON.stringify(data);
  }
  if (data.live && ["http:", "https:"].includes(location.protocol)) {
    liveStatus("connecting", "Conectando actualización automática…");
    const request = async (url) => {
      const response = await fetch(url, {cache: "no-store", signal: AbortSignal.timeout(3000)});
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      return response.json();
    };
    async function poll() {
      try {
        const status = await request(data.live.statusUrl);
        if (status.status === "invalid") liveStatus("invalid", "Cambios inválidos · última versión válida", status.error);
        else {
          if (status.revision !== data.revision) applyUpdate(await request(data.live.dataUrl));
          liveStatus("connected", "Actualización automática activa");
        }
      } catch {
        liveStatus("disconnected", "Sin conexión · reintentando");
      } finally {setTimeout(poll, data.live.intervalMs);}
    }
    poll();
    document.addEventListener("visibilitychange", () => {if (!document.hidden) $("live-status").textContent = "Comprobando cambios…";});
  } else liveStatus("static", "Copia local · sin sincronización");
})();
