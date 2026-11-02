(function () {
  var dataEl = document.getElementById('stats-data');
  if (!dataEl) return;

  var stats;
  try {
    stats = JSON.parse(dataEl.textContent || '{}');
  } catch (e) {
    return;
  }

  var palette = ['#e10600', '#1a4fff', '#1a9e4a', '#7a3cff', '#111111', '#d07000', '#00838a', '#777777'];

  if (window.Chart) {
    Chart.defaults.font.family = 'Consolas, "Lucida Console", "Courier New", monospace';
    Chart.defaults.color = '#111';
    Chart.defaults.borderColor = '#ddd';

    var daily = stats.daily || [];
    var dailyCanvas = document.getElementById('daily-chart');
    if (dailyCanvas && daily.length) {
      new Chart(dailyCanvas, {
        type: 'line',
        data: {
          labels: daily.map(function (d) { return d.label || d.day; }),
          datasets: [
            {
              label: 'hits',
              data: daily.map(function (d) { return d.hits; }),
              borderColor: '#e10600',
              backgroundColor: 'rgba(225, 6, 0, 0.12)',
              fill: true,
              tension: 0.15,
              pointRadius: daily.length > 60 ? 0 : 3,
              pointHoverRadius: 5,
              borderWidth: 2,
            },
            {
              label: 'unique visitors',
              data: daily.map(function (d) { return d.uniques; }),
              borderColor: '#1a4fff',
              backgroundColor: 'rgba(26, 79, 255, 0.08)',
              fill: false,
              tension: 0.15,
              pointRadius: daily.length > 60 ? 0 : 3,
              pointHoverRadius: 5,
              borderWidth: 2,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: { mode: 'index', intersect: false },
          plugins: {
            legend: { labels: { boxWidth: 10, font: { size: 11 } } },
          },
          scales: {
            x: {
              ticks: {
                maxTicksLimit: stats.bucket === 'hour' ? 8 : 12,
                maxRotation: stats.bucket === 'hour' ? 45 : 0,
                font: { size: 11 },
              },
              grid: { display: false },
            },
            y: {
              beginAtZero: true,
              ticks: { precision: 0, font: { size: 11 } },
            },
          },
        },
      });
    }

    var pagesCanvas = document.getElementById('pages-chart');
    var pagesLegend = document.getElementById('pages-legend');
    var allPages = stats.pages_all || [];
    if (allPages.length === 0 && stats.pages && stats.pages.length) {
      allPages = stats.pages.filter(function (p) { return p.kind !== 'other'; }).concat(stats.pages_other || []);
    }
    if (pagesCanvas && allPages.length) {
      var pagePalette = palette.concat(['#b35a00', '#5a2d82', '#0d7377', '#8a1a1a']);
      var fit = 12;
      var stacked = window.matchMedia && window.matchMedia('(max-width: 760px)').matches;
      if (pagesLegend && !stacked) {
        pagesLegend.innerHTML = '';
        var probe = document.createElement('li');
        probe.innerHTML = '<i></i><span class="path">/measure</span>';
        pagesLegend.appendChild(probe);
        var itemH = probe.getBoundingClientRect().height || 18;
        var avail = pagesLegend.clientHeight || pagesLegend.getBoundingClientRect().height || 256;
        pagesLegend.innerHTML = '';
        if (itemH > 0) {
          fit = Math.max(1, Math.min(12, Math.floor(avail / itemH)));
        }
      }
      var named;
      var rest;
      if (allPages.length <= fit) {
        named = allPages;
        rest = [];
      } else {
        named = allPages.slice(0, Math.max(1, fit - 1));
        rest = allPages.slice(named.length);
      }
      var slices = named.map(function (p) {
        return { path: p.path, hits: p.hits, kind: 'page' };
      });
      if (rest.length) {
        var otherHits = 0;
        rest.forEach(function (p) { otherHits += p.hits; });
        slices.push({ path: 'other', hits: otherHits, kind: 'other' });
      }
      var pageColors = slices.map(function (p, i) {
        if (p.kind === 'other') return '#777777';
        return pagePalette[i % pagePalette.length];
      });
      new Chart(pagesCanvas, {
        type: 'doughnut',
        data: {
          labels: slices.map(function (p) { return p.path; }),
          datasets: [{
            data: slices.map(function (p) { return p.hits; }),
            backgroundColor: pageColors,
            borderColor: '#fafafa',
            borderWidth: 2,
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
          },
        },
      });
      if (pagesLegend) {
        named.forEach(function (p, i) {
          var li = document.createElement('li');
          li.innerHTML = '<i></i><span class="path"></span>';
          li.querySelector('i').style.background = pageColors[i];
          li.querySelector('.path').textContent = p.path;
          pagesLegend.appendChild(li);
        });
        if (rest.length) {
          var otherLi = document.createElement('li');
          otherLi.className = 'admin-legend-other';
          otherLi.innerHTML = '<details id="pages-other"><summary><i></i><span class="path"></span></summary><div class="admin-other-panel"><table class="admin-table"><tbody></tbody></table></div></details>';
          otherLi.querySelector('summary i').style.background = '#777777';
          otherLi.querySelector('summary .path').textContent = 'other: ' + rest.length + ' paths';
          var tbody = otherLi.querySelector('tbody');
          rest.forEach(function (p) {
            var tr = document.createElement('tr');
            tr.innerHTML = '<td class="path"></td><td class="num"></td>';
            tr.cells[0].textContent = p.path;
            tr.cells[1].textContent = String(p.hits);
            tbody.appendChild(tr);
          });
          pagesLegend.appendChild(otherLi);
        }
      }
    }
  }

  var mapEl = document.getElementById('geo-map');
  if (mapEl && window.L) {
    var map = L.map(mapEl, {
      worldCopyJump: true,
      minZoom: 1,
      maxZoom: 6,
      scrollWheelZoom: false,
      attributionControl: false,
    });
    map.setView([20, 0], 2);
    setTimeout(function () { map.invalidateSize(); }, 200);

    var byIso = {};
    var maxHits = 1;
    (stats.countries || []).forEach(function (c) {
      byIso[c.code] = c;
      if (c.hits > maxHits) maxHits = c.hits;
    });

    function fillFor(hits) {
      if (!hits) return '#e8e8e4';
      var t = Math.log(hits + 1) / Math.log(maxHits + 1);
      var r = Math.round(241 + (225 - 241) * t);
      var g = Math.round(200 + (6 - 200) * t);
      var b = Math.round(196 + (0 - 196) * t);
      return 'rgb(' + r + ',' + g + ',' + b + ')';
    }

    fetch('/assets/world-110m.geojson')
      .then(function (res) { return res.ok ? res.json() : null; })
      .then(function (geo) {
        if (!geo) return;
        var layer = L.geoJSON(geo, {
          style: function (feat) {
            var iso = feat.properties && feat.properties.iso;
            var row = iso ? byIso[iso] : null;
            return {
              color: '#111',
              weight: 0.5,
              fillColor: fillFor(row ? row.hits : 0),
              fillOpacity: 1,
            };
          },
          onEachFeature: function (feat, lyr) {
            var props = feat.properties || {};
            var row = props.iso ? byIso[props.iso] : null;
            var label = props.name || props.iso || '';
            if (row) {
              label += ': ' + row.hits + ' hit' + (row.hits === 1 ? '' : 's');
            }
            if (label) lyr.bindTooltip(label, { sticky: true });
          },
        }).addTo(map);

        try {
          map.fitBounds(layer.getBounds().pad(0.04));
        } catch (e) {}

        (stats.map_points || []).forEach(function (p) {
          if (typeof p.lat !== 'number' || typeof p.lon !== 'number') return;
          var r = 4 + Math.log2(p.hits + 1) * 1.6;
          L.circleMarker([p.lat, p.lon], {
            radius: r,
            color: '#1a4fff',
            weight: 1.25,
            fillColor: '#1a4fff',
            fillOpacity: 0.72,
          }).bindTooltip(
            (p.city ? p.city + ', ' : '') + (p.country || p.country_code || '') + ': ' + p.hits + ' hit' + (p.hits === 1 ? '' : 's'),
            { sticky: true }
          ).addTo(map);
        });
      })
      .catch(function () {});
  }

  var PAGE_SIZE = 20;
  var tablePagers = [];

  function attachTablePager(button) {
    var box = button.closest('.admin-more');
    var wrap = box ? box.previousElementSibling : null;
    var table = wrap && wrap.querySelector ? wrap.querySelector('table.admin-table') : null;
    var tbody = table && table.tBodies[0];
    if (!box || !table || !tbody) return null;
    var shown = PAGE_SIZE;

    function apply() {
      var n = 0;
      Array.prototype.forEach.call(tbody.rows, function (tr) {
        if (tr.getAttribute('data-nomatch') === '1') {
          tr.hidden = true;
          return;
        }
        tr.hidden = n >= shown;
        n += 1;
      });
      box.hidden = n <= shown;
    }

    button.addEventListener('click', function () {
      shown += PAGE_SIZE;
      apply();
    });
    apply();
    var pager = { table: table, apply: apply, reset: function () { shown = PAGE_SIZE; apply(); } };
    tablePagers.push(pager);
    return pager;
  }

  Array.prototype.forEach.call(document.querySelectorAll('.js-table-more'), function (button) {
    attachTablePager(button);
  });

  var table = document.getElementById('asn-table');
  var search = document.getElementById('asn-search');
  if (table && table.tBodies[0]) {
    var tbody = table.tBodies[0];
    var asnPager = null;
    tablePagers.forEach(function (pager) {
      if (pager.table === table) asnPager = pager;
    });

    if (search) {
      search.addEventListener('input', function () {
        var q = search.value.toLowerCase().trim();
        Array.prototype.forEach.call(tbody.rows, function (tr) {
          if (q !== '' && tr.textContent.toLowerCase().indexOf(q) === -1) {
            tr.setAttribute('data-nomatch', '1');
          } else {
            tr.removeAttribute('data-nomatch');
          }
        });
        if (asnPager) asnPager.reset();
      });
    }

    Array.prototype.forEach.call(table.tHead.rows[0].cells, function (th, idx) {
      if (!th.classList.contains('sortable')) return;
      th.addEventListener('click', function () {
        var current = th.getAttribute('aria-sort');
        var dir = current === 'ascending' ? 'desc' : current === 'descending' ? 'asc' : (th.dataset.type === 'num' ? 'desc' : 'asc');
        Array.prototype.forEach.call(table.tHead.rows[0].cells, function (cell) {
          cell.removeAttribute('aria-sort');
        });
        th.setAttribute('aria-sort', dir === 'asc' ? 'ascending' : 'descending');
        var rows = Array.prototype.slice.call(tbody.rows);
        rows.sort(function (a, b) {
          var av = a.cells[idx] ? (a.cells[idx].dataset.sort || a.cells[idx].textContent) : '';
          var bv = b.cells[idx] ? (b.cells[idx].dataset.sort || b.cells[idx].textContent) : '';
          if (th.dataset.type === 'num') {
            var an = parseFloat(av) || 0;
            var bn = parseFloat(bv) || 0;
            return dir === 'asc' ? an - bn : bn - an;
          }
          av = String(av).toLowerCase();
          bv = String(bv).toLowerCase();
          if (av < bv) return dir === 'asc' ? -1 : 1;
          if (av > bv) return dir === 'asc' ? 1 : -1;
          return 0;
        });
        rows.forEach(function (row) { tbody.appendChild(row); });
        if (asnPager) asnPager.apply();
      });
    });
  }

  var more = document.getElementById('recent-more');
  var recentBody = document.getElementById('recent-body');
  if (more && recentBody) {
    more.addEventListener('click', function () {
      if (more.disabled) return;
      more.disabled = true;
      more.textContent = 'loading...';
      var params = new URLSearchParams({
        range: more.getAttribute('data-range') || '24h',
        load_recent: '1',
        before: more.getAttribute('data-before') || '0',
      });
      params.set('hide_bots', more.getAttribute('data-hide-bots') === '0' ? '0' : '1');
      fetch('/admin/?' + params.toString(), { credentials: 'same-origin' })
        .then(function (res) {
          if (!res.ok) throw new Error('load failed');
          return res.json();
        })
        .then(function (data) {
          if (data.html) {
            recentBody.insertAdjacentHTML('beforeend', data.html);
          }
          if (data.has_more && data.before) {
            more.setAttribute('data-before', String(data.before));
            more.disabled = false;
            more.textContent = 'load more';
          } else if (more.parentNode) {
            more.parentNode.remove();
          }
        })
        .catch(function () {
          more.disabled = false;
          more.textContent = 'load more';
        });
    });
  }
})();
