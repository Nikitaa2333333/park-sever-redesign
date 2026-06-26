// PATTERN, not a drop-in. Shows the three-level fallback data flow only.
// Rebuild the JSX with the HOST site's own card/section components and tokens.
import { useState, useEffect } from 'react';

// 1) Seed data baked into the bundle — renders instantly, survives any fetch failure.
//    Hand-write a few real posts here so dev/preview looks alive without a token.
const SEED_DATA = [
  {
    id: 'seed_1',
    category: 'General',
    date: '15 мая 2026',
    title: 'Заголовок запасной новости',
    text: 'Короткий текст, который показывается, пока/если ВК недоступен.',
    image: '/seed-1.webp',
    link: 'https://vk.com/your_group',
  },
  // ...add a handful
];

// Optional: map category keys → display labels (drop if flat feed).
const CATEGORY_LABELS: Record<string, string> = {
  General: 'Новости',
};

export default function NewsSection() {
  const [posts, setPosts] = useState<any[]>(SEED_DATA);
  const [category, setCategory] = useState<string>('all');

  // 2) On mount, try the live JSON. On success → swap. On any failure → keep SEED_DATA.
  useEffect(() => {
    let active = true;
    fetch('/vk-news.json')
      .then(res => (res.ok ? res.json() : Promise.reject(res.status)))
      .then(data => {
        if (active && Array.isArray(data) && data.length > 0) {
          setPosts(data); // optionally: pin a fixed first post, dedupe, etc.
        }
      })
      .catch(() => { /* swallow — SEED_DATA already in state */ });
    return () => { active = false; };
  }, []);

  const visible = category === 'all' ? posts : posts.filter(p => p.category === category);

  // 3) Render with the HOST design system. Below is a neutral placeholder structure.
  return (
    <section>
      {/* category filter bar — omit for a flat feed */}
      {/* news grid */}
      <div className="news-grid">
        {visible.map(item => (
          <a key={item.id} href={item.link || '#'} target="_blank" rel="noopener noreferrer">
            <img src={item.image} alt={item.title} loading="lazy" />
            <span>{item.date} · {CATEGORY_LABELS[item.category] ?? item.category}</span>
            <h3>{item.title}</h3>
            <p>{item.text}</p>
          </a>
        ))}
      </div>
    </section>
  );
}
