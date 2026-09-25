import { Link } from "react-router-dom";
import { BadgeCheck, MessageSquareText, Scissors } from "lucide-react";

import craftImage from "../../assets/about-craft.jpg";

import "./About.scss";

const visitSteps = [
  {
    number: "01",
    icon: MessageSquareText,
    title: "We listen first",
    description:
      "Your routine, hair type and preferred finish shape the plan before the clippers start.",
  },
  {
    number: "02",
    icon: Scissors,
    title: "We work with intent",
    description:
      "Every line, blend and transition is considered from the first pass to the final detail.",
  },
  {
    number: "03",
    icon: BadgeCheck,
    title: "We finish for real life",
    description:
      "You leave with a sharp result and practical advice to keep it looking right between visits.",
  },
];

const About = () => {
  return (
    <main className="about-page">
      <section className="about-page__header">
        <div className="container about-page__header-grid">
          <div>
            <span className="section-label">Inside Forge & Fade</span>

            <h1>
              Made for the person
              <br />
              <span>in the chair.</span>
            </h1>
          </div>

          <p>
            No rushed appointments and no one-style-fits-all cuts. We built a
            barbershop around attention, honest advice and work that still
            looks good weeks later.
          </p>
        </div>
      </section>

      <section className="about-page__story">
        <div className="container about-page__story-grid">
          <div className="about-page__story-image">
            <img
              src={craftImage}
              alt="Traditional barber tools arranged on a workbench"
            />

            <span>Tools change. Standards do not.</span>
          </div>

          <div className="about-page__story-content">
            <span className="section-label">Our Point of View</span>

            <h2 className="section-title">A barbershop should earn your trust.</h2>

            <p>
              A great cut begins before a blade touches hair. It begins with
              paying attention: to how you wear it, how much time you spend on
              it and how you want to feel when you walk out.
            </p>

            <p>
              Forge & Fade pairs that personal approach with disciplined
              technique. We respect the old-school fundamentals, use modern
              methods where they improve the result and never add complexity
              for show.
            </p>

            <Link to="/services" className="about-page__text-link">
              Explore our services
            </Link>
          </div>
        </div>
      </section>

      <section className="about-page__manifesto">
        <div className="container about-page__manifesto-content">
          <span aria-hidden="true">"</span>
          <blockquote>
            The best haircut does not compete with who you are. It brings the
            sharper version into focus.
          </blockquote>
          <p>Our approach to every appointment</p>
        </div>
      </section>

      <section className="about-page__visit">
        <div className="container">
          <div className="about-page__visit-header">
            <span className="section-label">The Experience</span>
            <h2 className="section-title">What happens in the chair.</h2>
            <p className="section-text">
              A straightforward process designed around your style, comfort
              and time.
            </p>
          </div>

          <ol className="about-page__steps">
            {visitSteps.map(({ number, icon: Icon, title, description }) => (
              <li className="about-step" key={number}>
                <div className="about-step__topline">
                  <span>{number}</span>
                  <Icon aria-hidden="true" strokeWidth={1.5} />
                </div>
                <h3>{title}</h3>
                <p>{description}</p>
              </li>
            ))}
          </ol>
        </div>
      </section>

      <section className="about-page__cta">
        <div className="container about-page__cta-content">
          <div>
            <span className="section-label">Your Next Cut</span>
            <h2>Come in with a plan. Leave with a look.</h2>
          </div>

          <Link to="/booking" className="btn btn-primary">
            Book Appointment
          </Link>
        </div>
      </section>
    </main>
  );
};

export default About;
