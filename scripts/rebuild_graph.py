"""Cache a doc-extraction chunk (if given) and rebuild the full graph from caches.

Use after a semantic doc re-extraction when `graphify` refuses an incremental merge
(e.g. "saved semantic node(s) are missing"). Run from the repo root with graphify's
interpreter, and delete the chunk file only after this script prints "wrote True":

    $(cat graphify-out/.graphify_python) scripts/rebuild_graph.py \
        graphify-out/.graphify_chunk_01.json <abs path of each extracted file> ...

Without arguments it only rebuilds from the existing caches (fails if a doc is uncached).
Then run `graphify export html`.
"""
import json
import sys
from pathlib import Path

from graphify.analyze import god_nodes, suggest_questions, surprising_connections
from graphify.build import build_from_json
from graphify.cache import check_semantic_cache, save_semantic_cache
from graphify.cli import _stamped_manifest_files
from graphify.cluster import cluster, score_all
from graphify.detect import detect, save_manifest
from graphify.export import to_json
from graphify.extract import collect_files, extract
from graphify.paths import out_path
from graphify.report import generate
from graphify.watch import _read_build_excludes, _read_build_gitignore

SPEC = r"C:\Users\andhi\.claude\skills\graphify\references\extraction-spec.md"

if len(sys.argv) > 2:
    chunk = json.loads(Path(sys.argv[1]).read_text(encoding="utf-8"))
    saved = save_semantic_cache(chunk["nodes"], chunk["edges"], chunk.get("hyperedges", []),
                                root=".", allowed_source_files=sys.argv[2:], prompt_file=SPEC)
    print("cached", saved)

det = detect(Path("."), extra_excludes=_read_build_excludes(out_path()), gitignore=_read_build_gitignore(out_path()))
code = []
for f in det["files"].get("code", []):
    code.extend(collect_files(Path(f)) if Path(f).is_dir() else [Path(f)])
ast = extract(code, cache_root=Path("."), root=Path("."))
docs = [f for k in ("document", "paper", "image") for f in det["files"].get(k, [])]
cn, ce, ch, un = check_semantic_cache(docs, root=".", prompt_file=SPEC)
print("uncached", un)
if un:
    raise SystemExit("uncached docs remain; extract them first")
for n in cn:
    n.setdefault("_origin", "semantic")
for e in ce:
    e.setdefault("_origin", "semantic")
seen = {n["id"] for n in ast["nodes"]}
nodes = list(ast["nodes"])
for n in cn:
    if n["id"] not in seen:
        nodes.append(n)
        seen.add(n["id"])
ex = {"nodes": nodes, "edges": ast["edges"] + ce, "hyperedges": ch, "input_tokens": 0, "output_tokens": 0}
G = build_from_json(ex, root=".", directed=False)
comms = cluster(G)
coh = score_all(G, comms)
labels = {cid: " & ".join(str(G.nodes[n].get("label", n))[:30] for n in sorted(m, key=lambda n: -G.degree(n))[:2])
          for cid, m in comms.items()}
print("wrote", to_json(G, comms, "graphify-out/graph.json", force=True, community_labels=labels))
Path("graphify-out/GRAPH_REPORT.md").write_text(
    generate(G, comms, coh, labels, god_nodes(G), surprising_connections(G, comms), det,
             {"input": 0, "output": 0}, ".", suggested_questions=suggest_questions(G, comms, labels)),
    encoding="utf-8")
Path("graphify-out/.graphify_labels.json").write_text(json.dumps({str(k): v for k, v in labels.items()}, ensure_ascii=False), encoding="utf-8")
save_manifest(_stamped_manifest_files(det["files"], ex, Path(".")), root=".",
              scan_corpus={f for fl in det["files"].values() for f in fl})
print(G.number_of_nodes(), G.number_of_edges())
