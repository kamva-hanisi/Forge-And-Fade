import { shopMediaUrl } from "../../utils/shopMedia";

export default function BarberPortfolio({ barbers, slug, onBook }) {
  const portfolios = barbers.filter((barber) => barber.portfolio?.length);
  if (!portfolios.length) return null;

  return (
    <section className="shop-portfolio" aria-labelledby="shop-portfolio">
      <div className="container">
        <h2 id="shop-portfolio" className="section-title" tabIndex={-1}>
          Our work
        </h2>
        <div className="shop-portfolio__barbers">
          {portfolios.map((barber) => (
            <article className="shop-portfolio__barber" key={barber.id}>
              <div className="shop-portfolio__intro">
                <div>
                  <h3>{barber.name}</h3>
                  {barber.bio && <p>{barber.bio}</p>}
                </div>
                {onBook && (
                  <button
                    type="button"
                    className="btn btn-outline"
                    onClick={() => onBook(barber.id)}
                  >
                    Book with {barber.name}
                  </button>
                )}
              </div>
              <ul className="shop-portfolio__photos">
                {barber.portfolio.map(({ image, caption }) => (
                  <li key={image}>
                    <figure>
                      <img
                        src={shopMediaUrl(slug, image)}
                        loading="lazy"
                        alt={caption ? "" : `A haircut by ${barber.name}`}
                      />
                      {caption && <figcaption>{caption}</figcaption>}
                    </figure>
                  </li>
                ))}
              </ul>
            </article>
          ))}
        </div>
      </div>
    </section>
  );
}
