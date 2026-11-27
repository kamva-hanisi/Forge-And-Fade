import { Link } from "react-router-dom";
import { BadgeCheck, MessageSquareText, Scissors } from "lucide-react";

import barberTools from "../../assets/barber-tools.jpg";
import { usePublicCatalog } from "../../hooks/useShopServices";
import { bookingLink, shopPath } from "../../utils/shopLinks";
import { shopMediaUrl } from "../../utils/shopMedia";

import "./About.scss";

const visitSteps = [
  {
    icon: MessageSquareText,
    title: "We listen first",
    description:
      "Your routine, hair type and preferred finish shape the plan before the clippers start.",
  },
  {
    icon: Scissors,
    title: "We work with intent",
    description:
      "Every line, blend and transition is considered from the first pass to the final detail.",
  },
  {
    icon: BadgeCheck,
    title: "We finish for real life",
    description:
      "You leave with a sharp result and practical advice to keep it looking right between visits.",
  },
];

const About = () => {
  const catalog = usePublicCatalog();
  return (
    <main className="about-page">
      <section className="about-page__header">
        <div className="container about-page__header-grid">
          <div>
            <span className="section-label">Inside {catalog.profile.name}</span>

            <h1>
              Made for the person
              <br />
              <span>in the chair.</span>
            </h1>
          </div>

          <p>
            No rushed appointments and no one-style-fits-all cuts. We built a barbering business
            around attention, honest advice and work that still looks good weeks later.
          </p>
        </div>
      </section>

      <section className="about-page__story">
        <div className="container about-page__story-grid">
          <div className="about-page__story-image">
            <img
              src={
                catalog.branding?.cover
                  ? shopMediaUrl(catalog.shop, catalog.branding.cover)
                  : barberTools
              }
              alt=""
              loading="lazy"
            />
          </div>

          <div className="about-page__story-content">
            <span className="section-label">Our Point of View</span>

            <h2 className="section-title">A barbering business should earn your trust.</h2>

            <p>
              A great cut begins before a blade touches hair. It begins with paying attention: to
              how you wear it, how much time you spend on it and how you want to feel when you walk
              out.
            </p>

            <p>
              {catalog.profile.name} pairs that personal approach with disciplined technique. We
              respect the old-school fundamentals, use modern methods where they improve the result
              and never add complexity for show.
            </p>

            <Link to="/services" className="about-page__text-link">
              Explore our services
            </Link>
          </div>
        </div>
      </section>

      <section className="about-page__team">
        <div className="container">
          <h2 className="section-title">Meet the barbers</h2>
          {catalog.loading && <p role="status">Loading barber profiles…</p>}
          {catalog.error && (
            <button className="btn btn-outline" type="button" onClick={catalog.retry}>
              Reload barber profiles
            </button>
          )}
          {!catalog.loading && !catalog.error && !catalog.barbers.length && (
            <p>No barber profiles are available.</p>
          )}
          <div className="about-page__team-grid">
            {catalog.barbers.map((barber) => {
              const photo = barber.portfolio?.[0];
              return (
                <article key={barber.id} className="about-page__barber">
                  {photo && (
                    <img
                      src={shopMediaUrl(catalog.shop, photo.image)}
                      alt={photo.caption || `A haircut by ${barber.name}`}
                      loading="lazy"
                    />
                  )}
                  <div>
                    <h3>{barber.name}</h3>
                    {barber.bio && <p>{barber.bio}</p>}
                    <Link
                      className="btn btn-outline"
                      to={bookingLink(catalog.shop, { barber: barber.id })}
                    >
                      Book with {barber.name}
                    </Link>
                  </div>
                </article>
              );
            })}
          </div>
        </div>
      </section>

      <section className="about-page__visit">
        <div className="container">
          <div className="about-page__visit-header">
            <span className="section-label">The Experience</span>
            <h2 className="section-title">What happens in the chair.</h2>
            <p className="section-text">
              A straightforward process designed around your style, comfort and time.
            </p>
          </div>

          <ol className="about-page__steps">
            {visitSteps.map(({ icon: Icon, title, description }) => (
              <li className="about-step" key={title}>
                <div className="about-step__topline">
                  <Icon aria-hidden="true" strokeWidth={1.5} />
                </div>
                <h3>{title}</h3>
                <p>{description}</p>
              </li>
            ))}
          </ol>
        </div>
      </section>

      <section className="about-page__first-visit">
        <div className="container">
          <h2 className="section-title">Before your first visit</h2>
          <ul>
            <li>Bring a reference photo if you have a cut or beard shape in mind.</li>
            <li>
              Tell your barber how you usually wear your hair and how much time you spend styling
              it.
            </li>
            <li>
              Let the business know in advance if you have a question about your visit or need
              assistance.
            </li>
          </ul>
          <Link to="/contact" className="btn btn-outline">
            Contact the business
          </Link>
        </div>
      </section>

      <section className="about-page__cta">
        <div className="container about-page__cta-content">
          <div>
            <span className="section-label">Your Next Cut</span>
            <h2>Come in with a plan. Leave with a look.</h2>
          </div>

          <Link to={shopPath(catalog.shop)} className="btn btn-primary">
            Book Appointment
          </Link>
        </div>
      </section>
    </main>
  );
};

export default About;
